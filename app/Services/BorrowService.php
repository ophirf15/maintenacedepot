<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\BorrowRequestLine;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowService
{
    /** Relations needed for readable request payloads (summary sentence, line labels). */
    public const DETAIL_RELATIONS = [
        'lines.item.toolType', 'lines.toolType', 'lines.allocatedItem.toolType',
        'requester', 'onBehalfOf', 'property', 'pickupDepot', 'loan',
    ];

    public function __construct(
        private ReferenceGenerator $refs,
        private AuditLogger $audit,
        private NotificationDispatcher $notifications,
    ) {}

    public function createDraft(User $user, array $data): BorrowRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $request = BorrowRequest::query()->create([
                'reference' => $this->refs->make('REQ'),
                'property_id' => $data['property_id'],
                'requester_id' => $user->id,
                'on_behalf_of_id' => $data['on_behalf_of_id'] ?? null,
                'pickup_depot_id' => $data['pickup_depot_id'],
                'status' => 'draft',
                'priority' => $data['priority'] ?? 'normal',
                'purpose' => $data['purpose'] ?? null,
                'needed_from' => $data['needed_from'],
                'needed_until' => $data['needed_until'],
                'expected_dropoff_at' => $data['expected_dropoff_at'] ?? $data['needed_until'],
            ]);

            foreach ($data['lines'] as $index => $line) {
                $this->assertNoConflict($line, $data['needed_from'], $data['needed_until']);

                BorrowRequestLine::query()->create([
                    'borrow_request_id' => $request->id,
                    'line_no' => $index + 1,
                    'request_mode' => $line['request_mode'],
                    'item_id' => $line['item_id'] ?? null,
                    'tool_type_id' => $line['tool_type_id'] ?? null,
                    'quantity' => $line['quantity'] ?? 1,
                    'notes' => $line['notes'] ?? null,
                    'status' => 'pending',
                ]);
            }

            $this->audit->log('created', $request, null, $request->toArray(), 'Borrow request drafted');

            return $request->load(['lines.item', 'lines.toolType', 'property', 'pickupDepot']);
        });
    }

    public function submit(BorrowRequest $request): BorrowRequest
    {
        $request->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->audit->log('submitted', $request, null, ['status' => 'submitted']);

        return $request->fresh(['lines', 'requester', 'property']);
    }

    public function approve(BorrowRequest $request, User $admin, array $payload): BorrowRequest
    {
        return DB::transaction(function () use ($request, $admin, $payload) {
            $request->load(['lines.item.toolType', 'lines.toolType']);

            $modified = false;
            $snapshot = $request->lines->toArray();
            $overrides = collect($payload['lines'] ?? [])->keyBy('id');

            foreach ($request->lines as $line) {
                $override = (array) $overrides->get($line->id, []);
                $decision = $override['status'] ?? 'allocated';

                if ($decision === 'rejected') {
                    $line->update([
                        'status' => 'rejected',
                        'reject_reason' => $override['reject_reason'] ?? 'Unavailable',
                    ]);
                    $modified = true;
                    $this->enqueueWaitlist($request, $line);
                    continue;
                }

                if ($decision === 'waitlisted') {
                    $line->update(['status' => 'waitlisted']);
                    $modified = true;
                    $this->enqueueWaitlist($request, $line);
                    continue;
                }

                $allocatedId = $override['allocated_item_id'] ?? $this->autoAllocate($line, $request);

                if (! $allocatedId) {
                    $line->update(['status' => 'waitlisted']);
                    $this->enqueueWaitlist($request, $line);
                    $modified = true;
                    continue;
                }

                if ($line->item_id && (int) $allocatedId !== (int) $line->item_id) {
                    $modified = true;
                }

                $line->update([
                    'allocated_item_id' => $allocatedId,
                    'status' => 'allocated',
                ]);
            }

            if ($this->dateChanged($payload['needed_from'] ?? null, $request->needed_from)) {
                $request->needed_from = Carbon::parse($payload['needed_from']);
                $modified = true;
            }
            if ($this->dateChanged($payload['needed_until'] ?? null, $request->needed_until)) {
                $request->needed_until = Carbon::parse($payload['needed_until']);
                $modified = true;
            }

            if ($modified && empty($payload['force_finalize'])) {
                $request->fill([
                    'status' => 'pending_modification_accept',
                    'modification_requested_at' => now(),
                    'modification_requested_by' => $admin->id,
                    'modification_note' => $payload['modification_note'] ?? 'Request was modified during approval',
                    'modification_snapshot' => $snapshot,
                    'approval_note' => $payload['approval_note'] ?? null,
                ])->save();

                $this->notifications->send('request.modified', $request->requester, [
                    'title' => 'Borrow request modified',
                    'body' => "Request {$request->reference} was modified and needs your acceptance.",
                ], '/requests/'.$request->id);

                return $request->fresh(self::DETAIL_RELATIONS);
            }

            return $this->finalizeApproval($request, $admin, $payload['approval_note'] ?? null);
        });
    }

    public function acceptModification(BorrowRequest $request, User $user): BorrowRequest
    {
        if ($request->requester_id !== $user->id && $request->on_behalf_of_id !== $user->id) {
            throw ValidationException::withMessages(['request' => 'Not allowed.']);
        }

        $request->update([
            'modification_accepted' => true,
        ]);

        return $this->finalizeApproval($request, $request->modification_requested_by
            ? User::find($request->modification_requested_by)
            : $user, $request->approval_note);
    }

    public function rejectModification(BorrowRequest $request, User $user): BorrowRequest
    {
        $request->update([
            'modification_accepted' => false,
            'status' => 'submitted',
            'modification_note' => 'Borrower rejected modification',
        ]);

        $this->audit->log('modification_rejected', $request);

        return $request->fresh(['lines']);
    }

    public function finalizeApproval(BorrowRequest $request, ?User $admin, ?string $note = null): BorrowRequest
    {
        $reservedStatus = CustomStatus::query()->where('slug', 'reserved')->first();
        $depot = Depot::query()->find($request->pickup_depot_id);

        $loan = Loan::query()->create([
            'reference' => $this->refs->make('LN'),
            'property_id' => $request->property_id,
            'borrow_request_id' => $request->id,
            'borrower_id' => $request->on_behalf_of_id ?: $request->requester_id,
            'depot_id' => $request->pickup_depot_id,
            'status' => 'reserved',
            'reserved_at' => now(),
            'due_at' => $request->needed_until,
            'original_due_at' => $request->needed_until,
        ]);

        foreach ($request->lines()->where('status', 'allocated')->get() as $line) {
            $item = Item::query()->find($line->allocated_item_id);
            if (! $item) {
                continue;
            }

            LoanItem::query()->create([
                'loan_id' => $loan->id,
                'item_id' => $item->id,
                'borrow_request_line_id' => $line->id,
                'status' => 'reserved',
            ]);

            if ($reservedStatus) {
                $item->update([
                    'custom_status_id' => $reservedStatus->id,
                    'current_property_id' => $request->property_id,
                ]);
            }

            $line->update(['status' => 'fulfilled']);
        }

        $pickupDeadline = null;
        if ($depot?->pickup_window_enabled) {
            $pickupDeadline = now()->addHours($depot->pickup_window_hours);
        }

        $request->update([
            'status' => 'reserved',
            'approved_at' => now(),
            'approved_by' => $admin?->id,
            'approval_note' => $note,
            'reserved_at' => now(),
            'pickup_deadline_at' => $pickupDeadline,
        ]);

        $this->audit->log('approved', $request, null, ['loan' => $loan->reference]);
        $this->notifications->send('request.approved', $request->requester, [
            'title' => 'Request approved',
            'body' => "Request {$request->reference} is reserved. Loan {$loan->reference}.",
        ], '/loans/'.$loan->id);

        return $request->fresh(self::DETAIL_RELATIONS);
    }

    protected function assertNoConflict(array $line, $from, $until): void
    {
        if (($line['request_mode'] ?? null) !== 'specific_item' || empty($line['item_id'])) {
            return;
        }

        $item = Item::query()->with('status')->find($line['item_id']);
        if (! $item || ! $item->isAvailableForBorrow()) {
            if ($item?->toolType?->allow_waitlist ?? true) {
                return;
            }
            throw ValidationException::withMessages([
                'lines' => "Item {$item?->asset_tag} is not available.",
            ]);
        }

        $conflict = LoanItem::query()
            ->where('item_id', $item->id)
            ->whereIn('status', ['reserved', 'checked_out'])
            ->whereHas('loan', function ($q) use ($from, $until) {
                $q->where('due_at', '>', $from)
                    ->where('reserved_at', '<', $until)
                    ->whereNotIn('status', ['closed', 'cancelled']);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'lines' => "Item {$item->asset_tag} is already reserved/checked out for that window. You can join the waitlist.",
            ]);
        }
    }

    /**
     * Units of a tool type that can be handed out right now: loanable, in an
     * "available" status, and not tied up by another live loan.
     */
    public function availableUnits(?int $toolTypeId, ?int $depotId = null, ?int $exceptLoanItemsOf = null): Collection
    {
        if (! $toolTypeId) {
            return collect();
        }

        $availableStatusIds = CustomStatus::query()
            ->where('availability_effect', 'available')
            ->pluck('id');

        return Item::query()
            ->with(['status', 'depot', 'specValues.field'])
            ->where('tool_type_id', $toolTypeId)
            ->where('is_loanable', true)
            ->whereIn('custom_status_id', $availableStatusIds)
            ->whereDoesntHave('loanItems', fn (Builder $q) => $this->liveLoanItem($q, $exceptLoanItemsOf))
            ->when($depotId, fn (Builder $q) => $q->orderByRaw('depot_id = ? desc', [$depotId]))
            ->orderBy('asset_tag')
            ->get();
    }

    /**
     * Suggested allocation for a line: keep the requested unit when it is still
     * free, otherwise fall back to any free unit of the same tool type.
     */
    public function suggestAllocation(BorrowRequestLine $line, ?int $depotId = null): ?Item
    {
        $line->loadMissing('item');

        if ($line->item_id && $this->isUnitFree($line->item_id)) {
            return $line->item;
        }

        $toolTypeId = $line->tool_type_id ?: $line->item?->tool_type_id;

        return $this->availableUnits($toolTypeId, $depotId)->first();
    }

    public function isUnitFree(int $itemId): bool
    {
        $item = Item::query()->with('status')->find($itemId);

        if (! $item || ! $item->isAvailableForBorrow()) {
            return false;
        }

        return ! LoanItem::query()
            ->where('item_id', $itemId)
            ->where(fn (Builder $q) => $this->liveLoanItem($q))
            ->exists();
    }

    protected function autoAllocate(BorrowRequestLine $line, BorrowRequest $request): ?int
    {
        return $this->suggestAllocation($line, $request->pickup_depot_id)?->id;
    }

    protected function liveLoanItem(Builder $query, ?int $exceptLoanId = null): Builder
    {
        return $query->whereIn('status', ['reserved', 'checked_out'])
            ->when($exceptLoanId, fn (Builder $q) => $q->where('loan_id', '!=', $exceptLoanId))
            ->whereHas('loan', fn (Builder $q) => $q->whereNotIn('status', ['closed', 'cancelled']));
    }

    /**
     * Date pickers only send minutes, so a re-posted date is not a real change.
     * Comparing at minute granularity keeps untouched approvals out of the
     * "requester must accept changes" loop.
     */
    protected function dateChanged(?string $incoming, $current): bool
    {
        if (! $incoming) {
            return false;
        }

        if (! $current) {
            return true;
        }

        return ! Carbon::parse($incoming)->startOfMinute()
            ->equalTo(Carbon::parse($current)->startOfMinute());
    }

    protected function enqueueWaitlist(BorrowRequest $request, BorrowRequestLine $line): void
    {
        $position = WaitlistEntry::query()
            ->where('tool_type_id', $line->tool_type_id)
            ->where('item_id', $line->item_id)
            ->where('status', 'waiting')
            ->max('position') + 1;

        WaitlistEntry::query()->create([
            'property_id' => $request->property_id,
            'user_id' => $request->requester_id,
            'borrow_request_id' => $request->id,
            'borrow_request_line_id' => $line->id,
            'item_id' => $line->item_id,
            'tool_type_id' => $line->tool_type_id,
            'position' => $position ?: 1,
            'desired_from' => $request->needed_from,
            'desired_until' => $request->needed_until,
            'status' => 'waiting',
        ]);
    }
}
