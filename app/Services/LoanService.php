<?php

namespace App\Services;

use App\Models\ChecklistResponse;
use App\Models\CustomStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanConsumableIssue;
use App\Models\LoanExtension;
use App\Models\LoanItem;
use App\Models\OfflineScanEvent;
use App\Models\ReturnInspection;
use App\Models\Ticket;
use App\Models\User;
use App\Services\QrLabelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(
        private ReferenceGenerator $refs,
        private AuditLogger $audit,
        private NotificationDispatcher $notifications,
        private SettingsService $settings,
        private MaintenancePlanService $maintenance,
        private StockService $stock,
    ) {}

    public function checkout(Loan $loan, User $actor, array $payload): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $payload) {
            $checkedOut = CustomStatus::query()->where('slug', 'checked-out')->first();
            $override = (bool) ($payload['maintenance_override'] ?? false);
            $overrideReason = $payload['maintenance_override_reason'] ?? null;

            foreach ($payload['items'] as $row) {
                $loanItem = $loan->items()->where('item_id', $row['item_id'])->firstOrFail();
                $item = $loanItem->item()->with('toolType')->first();

                $this->assertScanMatches($item, $row['qr_token'] ?? null);
                $this->assertMaintenanceAllowsCheckout($item, $override, $actor, $overrideReason);

                if (! empty($payload['checklist'])) {
                    ChecklistResponse::query()->create([
                        'checklist_template_id' => $payload['checklist']['template_id'],
                        'context' => 'checkout',
                        'item_id' => $item->id,
                        'loan_id' => $loan->id,
                        'completed_by' => $actor->id,
                        'completed_at' => now(),
                        'result' => $payload['checklist']['result'] ?? 'pass',
                        'answers' => $payload['checklist']['answers'] ?? [],
                    ]);
                }

                $loanItem->update([
                    'status' => 'checked_out',
                    'checked_out_at' => now(),
                    'condition_out' => $row['condition_out'] ?? $item->condition,
                    'fuel_pct_out' => $row['fuel_pct_out'] ?? $item->fuel_pct,
                    'usage_hours_out' => $item->usage_hours,
                ]);

                $this->maintenance->recordCheckout($item);

                if ($checkedOut) {
                    $item->update(['custom_status_id' => $checkedOut->id]);
                }
            }

            $primaryByItemId = $loan->items()
                ->whereNull('companion_of_loan_item_id')
                ->get()
                ->keyBy('item_id');

            foreach ($payload['companions'] ?? [] as $row) {
                $this->attachCompanion($loan, $actor, $row, $primaryByItemId, $checkedOut, $override, $overrideReason);
            }

            foreach ($payload['consumables'] ?? [] as $row) {
                $this->attachConsumableEstimate($loan, $row, $primaryByItemId);
            }

            $loan->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
                'checked_out_by' => $actor->id,
                'checkout_notes' => $payload['notes'] ?? null,
            ]);

            $this->audit->log('checked_out', $loan);

            return $loan->fresh([
                'items.item.toolType',
                'items.companionOf',
                'consumableIssues.item',
                'borrower',
            ]);
        });
    }

    public function selfReturn(Loan $loan, User $actor, array $payload): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $payload) {
            foreach ($payload['items'] as $row) {
                $loanItem = $loan->items()->where('item_id', $row['item_id'])->firstOrFail();

                $reading = isset($row['usage_hours_reading']) ? (float) $row['usage_hours_reading'] : null;
                $estimate = isset($row['usage_hours_estimate']) ? (float) $row['usage_hours_estimate'] : null;
                $projectedIn = null;
                $delta = null;

                if ($reading !== null) {
                    $baseline = (float) ($loanItem->usage_hours_out ?? 0);
                    $delta = max(0, $reading - $baseline);
                    $projectedIn = $reading;
                } elseif ($estimate !== null) {
                    $delta = $estimate;
                    $projectedIn = $loanItem->usage_hours_out !== null
                        ? (float) $loanItem->usage_hours_out + $estimate
                        : null;
                }

                ReturnInspection::query()->create([
                    'loan_id' => $loan->id,
                    'loan_item_id' => $loanItem->id,
                    'item_id' => $loanItem->item_id,
                    'inspected_by' => $actor->id,
                    'is_self_return' => true,
                    'admin_reviewed' => false,
                    'inspected_at' => now(),
                    'condition' => $row['condition'] ?? null,
                    'fuel_pct' => $row['fuel_pct'] ?? null,
                    'usage_hours_estimate' => $estimate,
                    'usage_hours_reading' => $reading,
                    'damage_found' => (bool) ($row['damage_found'] ?? false),
                    'damage_description' => $row['damage_description'] ?? null,
                    'end_of_life_soon' => (bool) ($row['end_of_life_soon'] ?? false),
                    'notes' => $row['notes'] ?? null,
                ]);

                $loanItem->update([
                    'status' => 'returned',
                    'returned_at' => now(),
                    'condition_in' => $row['condition'] ?? null,
                    'fuel_pct_in' => $row['fuel_pct'] ?? null,
                    'usage_hours_in' => $projectedIn,
                    'usage_hours_delta' => $delta,
                ]);
            }

            $loan->update([
                'status' => 'return_pending',
                'return_requested_at' => now(),
                'return_notes' => $payload['notes'] ?? null,
            ]);

            $this->audit->log('return_requested', $loan);

            return $loan->fresh(['items.item.toolType', 'items.inspection']);
        });
    }

    public function reviewReturn(Loan $loan, User $admin, array $payload): Loan
    {
        return DB::transaction(function () use ($loan, $admin, $payload) {
            $available = CustomStatus::query()->where('slug', 'available')->first();
            $oos = CustomStatus::query()->where('slug', 'out-of-service')->first();

            foreach ($payload['items'] as $row) {
                $loanItem = $loan->items()->where('item_id', $row['item_id'])->firstOrFail();
                $item = $loanItem->item;
                $inspection = $loanItem->inspection;

                $takeOut = (bool) ($row['take_out_of_service'] ?? false);
                $fail = ($row['overall_result'] ?? 'pass') === 'fail';
                $damageFound = (bool) ($row['damage_found'] ?? $inspection?->damage_found ?? false);
                if ($takeOut || $fail) {
                    $damageFound = true;
                }

                $reading = array_key_exists('usage_hours_reading', $row)
                    ? (isset($row['usage_hours_reading']) ? (float) $row['usage_hours_reading'] : null)
                    : ($inspection?->usage_hours_reading !== null ? (float) $inspection->usage_hours_reading : null);
                $estimate = array_key_exists('usage_hours_estimate', $row)
                    ? (isset($row['usage_hours_estimate']) ? (float) $row['usage_hours_estimate'] : null)
                    : ($inspection?->usage_hours_estimate !== null ? (float) $inspection->usage_hours_estimate : null);

                $data = [
                    'inspected_by' => $admin->id,
                    'admin_reviewed' => true,
                    'inspected_at' => now(),
                    'overall_result' => $row['overall_result'] ?? 'pass',
                    'condition' => $row['condition'] ?? $inspection?->condition,
                    'fuel_pct' => $row['fuel_pct'] ?? $inspection?->fuel_pct,
                    'usage_hours_estimate' => $estimate,
                    'usage_hours_reading' => $reading,
                    'damage_found' => $damageFound,
                    'damage_description' => $row['damage_description'] ?? $inspection?->damage_description,
                    'end_of_life_soon' => (bool) ($row['end_of_life_soon'] ?? $inspection?->end_of_life_soon ?? false),
                    'notes' => $row['notes'] ?? null,
                ];

                if ($inspection) {
                    $inspection->update($data);
                } else {
                    $inspection = ReturnInspection::query()->create(array_merge($data, [
                        'loan_id' => $loan->id,
                        'loan_item_id' => $loanItem->id,
                        'item_id' => $item->id,
                        'is_self_return' => false,
                    ]));
                }

                $usage = $this->maintenance->recordUsage(
                    $item,
                    $reading,
                    $estimate,
                    $loanItem->usage_hours_out !== null ? (float) $loanItem->usage_hours_out : null,
                    $loanItem->fuel_pct_out !== null ? (int) $loanItem->fuel_pct_out : null,
                    isset($data['fuel_pct']) ? (int) $data['fuel_pct'] : null,
                );

                $item = $usage['item'];
                $hoursDelta = $usage['hours_delta'];

                $itemUpdates = [
                    'condition' => $data['condition'] ?? $item->condition,
                    'end_of_life_soon' => $data['end_of_life_soon'],
                    'current_property_id' => null,
                    'depot_id' => $loan->depot_id,
                ];

                if ($damageFound && $takeOut && $oos) {
                    $itemUpdates['custom_status_id'] = $oos->id;
                    $ticket = Ticket::query()->create([
                        'reference' => $this->refs->make('TK'),
                        'item_id' => $item->id,
                        'loan_id' => $loan->id,
                        'ticket_type' => 'defect',
                        'title' => 'Damage on return: '.$item->asset_tag,
                        'description' => $data['damage_description'],
                        'severity' => $row['severity'] ?? 'medium',
                        'status' => 'open',
                        'takes_out_of_service' => true,
                        'reported_by' => $admin->id,
                    ]);
                    $inspection->update(['ticket_id' => $ticket->id]);
                    $loan->update(['damage_reported' => true]);
                } elseif ($available && ! $takeOut) {
                    $itemUpdates['custom_status_id'] = $available->id;
                }

                if (! empty($row['lifespan_years'])) {
                    $itemUpdates['lifespan_years'] = $row['lifespan_years'];
                }

                $item->update($itemUpdates);

                $loanItem->update([
                    'status' => 'returned',
                    'returned_at' => now(),
                    'condition_in' => $data['condition'],
                    'fuel_pct_in' => $data['fuel_pct'],
                    'usage_hours_delta' => $hoursDelta,
                    'usage_hours_in' => $reading ?? (
                        $loanItem->usage_hours_out !== null
                            ? (float) $loanItem->usage_hours_out + $hoursDelta
                            : null
                    ),
                ]);
            }

            foreach ($payload['consumables'] ?? [] as $row) {
                $issue = LoanConsumableIssue::query()
                    ->where('loan_id', $loan->id)
                    ->where('id', $row['id'])
                    ->firstOrFail();
                $qtyUsed = array_key_exists('qty_used', $row)
                    ? (float) $row['qty_used']
                    : (float) $issue->qty_estimated;
                $this->stock->confirmIssue(
                    $issue,
                    $qtyUsed,
                    $admin,
                    (bool) ($row['allow_negative'] ?? false),
                    $row['notes'] ?? null,
                );
            }

            // Auto-confirm any remaining estimated issues at their estimate.
            foreach ($loan->consumableIssues()->where('status', 'estimated')->get() as $issue) {
                $this->stock->confirmIssue($issue, (float) $issue->qty_estimated, $admin);
            }

            $loan->update([
                'status' => 'closed',
                'returned_at' => now(),
                'received_by' => $admin->id,
                'closed_at' => now(),
            ]);

            if ($loan->borrowRequest) {
                $loan->borrowRequest->update(['status' => 'completed']);
            }

            $this->audit->log('returned', $loan);

            return $loan->fresh([
                'items.item.toolType',
                'items.inspection',
                'items.companionOf',
                'consumableIssues.item',
            ]);
        });
    }

    public function requestExtension(Loan $loan, User $user, array $data): LoanExtension
    {
        $contested = LoanItem::query()
            ->whereIn('item_id', $loan->items()->pluck('item_id'))
            ->where('loan_id', '!=', $loan->id)
            ->whereIn('status', ['reserved'])
            ->exists();

        if ($contested) {
            throw ValidationException::withMessages([
                'extension' => 'Cannot extend; another reservation exists for one or more items.',
            ]);
        }

        $extension = LoanExtension::query()->create([
            'loan_id' => $loan->id,
            'requested_by' => $user->id,
            'previous_due_at' => $loan->due_at,
            'requested_due_at' => $data['requested_due_at'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        $this->notifications->send('loan.extension_requested', $loan->borrower, [
            'title' => 'Extension requested',
            'body' => "Extension requested for loan {$loan->reference}",
        ]);

        return $extension;
    }

    public function decideExtension(LoanExtension $extension, User $admin, bool $approve, ?string $note = null): LoanExtension
    {
        $extension->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_due_at' => $approve ? $extension->requested_due_at : null,
            'decided_by' => $admin->id,
            'decided_at' => now(),
        ]);

        if ($approve) {
            $extension->loan->update([
                'due_at' => $extension->requested_due_at,
                'extension_count' => $extension->loan->extension_count + 1,
                'status' => 'checked_out',
            ]);
        }

        return $extension->fresh('loan');
    }

    /**
     * Staff walk-in: create a reserved loan for an available item and check it out immediately.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWalkInCheckout(User $actor, array $data): Loan
    {
        return DB::transaction(function () use ($actor, $data) {
            $item = $this->resolveItemForAdHoc($data);
            $borrower = $this->resolveActiveBorrower((int) $data['borrower_id']);
            $this->assertItemNotOnOpenLoan($item);

            if (! $item->is_loanable || $item->is_consumable) {
                throw ValidationException::withMessages([
                    'item_id' => 'This tool cannot be borrowed.',
                ]);
            }

            $propertyId = (int) ($data['property_id'] ?? $borrower->default_property_id);
            $depotId = (int) ($data['depot_id'] ?? $item->depot_id);
            if (! $propertyId || ! $depotId) {
                throw ValidationException::withMessages([
                    'property_id' => 'Property and depot are required for a walk-in loan.',
                ]);
            }

            $notes = trim((string) ($data['notes'] ?? '')) ?: 'Walk-in checkout';
            $loan = $this->createAdHocLoan($borrower, $item, $propertyId, $depotId, $data['due_at'], $notes);
            $this->markItemReserved($item, $propertyId);

            $loan = $this->checkout($loan->fresh(['items']), $actor, [
                'items' => [[
                    'item_id' => $item->id,
                    'qr_token' => $data['qr_token'] ?? $item->qr_token,
                    'condition_out' => $data['condition_out'] ?? $item->condition,
                    'fuel_pct_out' => $data['fuel_pct_out'] ?? $item->fuel_pct,
                ]],
                'maintenance_override' => (bool) ($data['maintenance_override'] ?? false),
                'maintenance_override_reason' => $data['maintenance_override_reason'] ?? null,
                'notes' => $notes,
            ]);

            $this->audit->log('walk_in_checkout', $loan, null, [
                'borrower_id' => $borrower->id,
                'item_id' => $item->id,
                'notes' => $notes,
            ]);

            return $loan->fresh(['borrower', 'depot', 'property', 'items.item.toolType']);
        });
    }

    /**
     * Staff orphan return: invent a short loan, check out, then inspect/close so usage is logged.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrphanReturn(User $actor, array $data): Loan
    {
        return DB::transaction(function () use ($actor, $data) {
            $item = $this->resolveItemForAdHoc($data);
            $borrower = $this->resolveActiveBorrower((int) $data['borrower_id']);
            $this->assertItemNotOnOpenLoan($item);

            $propertyId = (int) ($data['property_id'] ?? $borrower->default_property_id);
            $depotId = (int) ($data['depot_id'] ?? $item->depot_id);
            if (! $propertyId || ! $depotId) {
                throw ValidationException::withMessages([
                    'property_id' => 'Property and depot are required for an orphan return.',
                ]);
            }

            $notes = trim((string) ($data['notes'] ?? ''))
                ?: 'Orphan return — no prior checkout recorded';
            $dueAt = $data['due_at'] ?? now()->toDateTimeString();

            $loan = $this->createAdHocLoan($borrower, $item, $propertyId, $depotId, $dueAt, $notes);
            $this->markItemReserved($item, $propertyId);

            $loan = $this->checkout($loan->fresh(['items']), $actor, [
                'items' => [[
                    'item_id' => $item->id,
                    'qr_token' => $data['qr_token'] ?? $item->qr_token,
                    'condition_out' => $data['condition_out'] ?? $item->condition,
                    'fuel_pct_out' => $data['fuel_pct_out'] ?? $item->fuel_pct,
                ]],
                // Tool is physically back; do not block recording inbound condition.
                'maintenance_override' => true,
                'maintenance_override_reason' => 'Orphan return — recording inbound condition',
                'notes' => $notes,
            ]);

            $loan = $this->reviewReturn($loan->fresh(['items']), $actor, [
                'items' => [[
                    'item_id' => $item->id,
                    'overall_result' => $data['overall_result'] ?? 'pass',
                    'condition' => $data['condition'] ?? $item->condition,
                    'fuel_pct' => $data['fuel_pct'] ?? $item->fuel_pct,
                    'usage_hours_estimate' => $data['usage_hours_estimate'] ?? null,
                    'usage_hours_reading' => $data['usage_hours_reading'] ?? null,
                    'damage_found' => (bool) ($data['damage_found'] ?? false),
                    'damage_description' => $data['damage_description'] ?? null,
                    'end_of_life_soon' => (bool) ($data['end_of_life_soon'] ?? false),
                    'take_out_of_service' => (bool) ($data['take_out_of_service'] ?? false),
                    'severity' => $data['severity'] ?? null,
                    'notes' => $notes,
                ]],
            ]);

            $loan->update(['return_notes' => $notes]);

            $this->audit->log('orphan_return', $loan, null, [
                'borrower_id' => $borrower->id,
                'item_id' => $item->id,
                'notes' => $notes,
            ]);

            return $loan->fresh([
                'borrower',
                'depot',
                'property',
                'items.item.toolType',
                'items.inspection',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveItemForAdHoc(array $data): Item
    {
        if (! empty($data['item_id'])) {
            return Item::query()->with(['status', 'toolType'])->findOrFail($data['item_id']);
        }

        if (! empty($data['qr_token'])) {
            return QrLabelService::findItemByScanCode($data['qr_token'])
                ?? throw ValidationException::withMessages(['qr_token' => 'No tool matches that code.']);
        }

        throw ValidationException::withMessages(['item_id' => 'Provide item_id or qr_token.']);
    }

    protected function resolveActiveBorrower(int $borrowerId): User
    {
        $borrower = User::query()->where('id', $borrowerId)->where('is_active', true)->first();
        if (! $borrower) {
            throw ValidationException::withMessages(['borrower_id' => 'Borrower not found or inactive.']);
        }

        return $borrower;
    }

    protected function assertItemNotOnOpenLoan(Item $item): void
    {
        $open = LoanItem::query()
            ->where('item_id', $item->id)
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['reserved', 'checked_out', 'return_pending']))
            ->with('loan:id,reference,status')
            ->first();

        if ($open) {
            throw ValidationException::withMessages([
                'item_id' => "This tool is already on loan {$open->loan->reference}. Open that loan instead.",
            ]);
        }
    }

    protected function createAdHocLoan(
        User $borrower,
        Item $item,
        int $propertyId,
        int $depotId,
        mixed $dueAt,
        string $notes,
    ): Loan {
        $loan = Loan::query()->create([
            'reference' => $this->refs->make('LN'),
            'property_id' => $propertyId,
            'borrower_id' => $borrower->id,
            'depot_id' => $depotId,
            'status' => 'reserved',
            'reserved_at' => now(),
            'due_at' => $dueAt,
            'original_due_at' => $dueAt,
            'checkout_notes' => $notes,
        ]);

        LoanItem::query()->create([
            'loan_id' => $loan->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        return $loan;
    }

    protected function markItemReserved(Item $item, int $propertyId): void
    {
        $reserved = CustomStatus::query()->where('slug', 'reserved')->first();
        if ($reserved) {
            $item->update([
                'custom_status_id' => $reserved->id,
                'current_property_id' => $propertyId,
            ]);
        }
    }

    public function syncOfflineScan(User $user, array $event): OfflineScanEvent
    {
        $record = OfflineScanEvent::query()->firstOrCreate(
            ['user_id' => $user->id, 'client_uuid' => $event['client_uuid']],
            [
                'action' => $event['action'],
                'qr_token' => $event['qr_token'],
                'loan_id' => $event['loan_id'] ?? null,
                'payload' => $event['payload'] ?? [],
                'scanned_at' => $event['scanned_at'] ?? now(),
                'status' => 'pending',
            ]
        );

        if ($record->synced_at) {
            return $record;
        }

        try {
            $item = QrLabelService::findItemByScanCode($event['qr_token'])
                ?? throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Item::class);
            $loan = $this->resolveLoanForScan($item, $event['loan_id'] ?? null, $event['action']);

            if ($event['action'] === 'checkout') {
                $this->checkout($loan, $user, [
                    'items' => [['item_id' => $item->id, 'qr_token' => $item->qr_token]],
                    'maintenance_override' => $event['payload']['maintenance_override'] ?? false,
                    'maintenance_override_reason' => $event['payload']['maintenance_override_reason'] ?? 'Offline scan override',
                ]);
            } elseif ($event['action'] === 'return') {
                $this->selfReturn($loan, $user, [
                    'items' => [[
                        'item_id' => $item->id,
                        'condition' => $event['payload']['condition'] ?? null,
                        'fuel_pct' => $event['payload']['fuel_pct'] ?? null,
                        'usage_hours_estimate' => $event['payload']['usage_hours_estimate'] ?? null,
                        'usage_hours_reading' => $event['payload']['usage_hours_reading'] ?? null,
                    ]],
                ]);
            }

            $record->update([
                'loan_id' => $loan->id,
                'status' => 'synced',
                'synced_at' => now(),
                'error_message' => null,
            ]);

            $this->audit->log('offline_sync', $loan, null, [
                'action' => $event['action'],
                'client_uuid' => $event['client_uuid'],
                'item_id' => $item->id,
            ]);
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            $this->audit->log('offline_sync_failed', null, null, [
                'action' => $event['action'],
                'client_uuid' => $event['client_uuid'],
                'error' => $e->getMessage(),
            ]);
        }

        return $record->fresh();
    }

    protected function resolveLoanForScan(Item $item, mixed $loanId, string $action): Loan
    {
        $statuses = $action === 'checkout' ? ['reserved'] : ['checked_out'];
        $itemStatuses = $statuses === ['reserved'] ? ['reserved'] : ['checked_out', 'reserved'];

        // Prefer the loan that already contains this scanned item — never trust a
        // client-supplied loan_id that does not include the QR'd asset.
        $loan = Loan::query()
            ->whereIn('status', $statuses)
            ->whereHas('items', fn ($q) => $q->where('item_id', $item->id)->whereIn('status', $itemStatuses))
            ->when($loanId, fn ($q) => $q->where('id', $loanId))
            ->orderByDesc('id')
            ->first();

        if (! $loan) {
            throw ValidationException::withMessages([
                'loan_id' => 'No active loan found for this item QR code.',
            ]);
        }

        return $loan;
    }

    protected function assertMaintenanceAllowsCheckout(Item $item, bool $override, User $actor, ?string $reason = null): void
    {
        $summary = $this->maintenance->summarizeOverdue($item);

        if ($summary['overdue']->isEmpty()) {
            return;
        }

        if ($override) {
            $this->audit->log('maintenance_override', $item, null, [
                'plans' => $summary['overdue']->pluck('id'),
                'blocking_plans' => $summary['blocking']->pluck('id'),
                'by' => $actor->id,
                'reason' => $reason,
            ], $reason ?: 'Checkout allowed with maintenance override');

            return;
        }

        if ($summary['blocking']->isNotEmpty()) {
            throw ValidationException::withMessages([
                'maintenance' => 'Item has overdue maintenance that blocks pick-up. Override with a reason is required.',
            ]);
        }
    }

    protected function assertScanMatches(Item $item, ?string $qrToken): void
    {
        if ($qrToken === null || $qrToken === '') {
            return;
        }

        $scanned = trim($qrToken);
        $matchesToken = strtolower($scanned) === strtolower((string) $item->qr_token);
        $matchesTag = strtolower($scanned) === strtolower((string) $item->asset_tag);
        $matchesNumeric = preg_match('/^\d{6}$/', $scanned) === 1
            && $scanned === (string) $item->numeric_code;
        if (! $matchesToken && ! $matchesTag && ! $matchesNumeric) {
            throw ValidationException::withMessages(['qr' => 'Code does not match this tool.']);
        }
    }

    /**
     * @param  Collection<int, LoanItem>  $primaryByItemId
     * @param  array<string, mixed>  $row
     */
    protected function attachCompanion(
        Loan $loan,
        User $actor,
        array $row,
        Collection $primaryByItemId,
        ?CustomStatus $checkedOut,
        bool $override,
        ?string $overrideReason,
    ): void {
        $companionItem = Item::query()->with(['status', 'toolType'])->findOrFail($row['item_id']);
        if ($companionItem->is_consumable) {
            throw ValidationException::withMessages([
                'companions' => 'Consumable SKUs cannot be attached as companions. Use consumables qty instead.',
            ]);
        }

        $primaryItemId = (int) ($row['companion_of_item_id'] ?? 0);
        $primaryLoanItem = $primaryByItemId->get($primaryItemId);
        if (! $primaryLoanItem) {
            throw ValidationException::withMessages([
                'companions' => 'Companion must be paired to a primary item already on this loan.',
            ]);
        }

        $this->assertScanMatches($companionItem, $row['qr_token'] ?? null);
        $this->assertMaintenanceAllowsCheckout($companionItem, $override, $actor, $overrideReason);

        $existing = $loan->items()->where('item_id', $companionItem->id)->first();
        if ($existing) {
            $existing->update([
                'companion_of_loan_item_id' => $primaryLoanItem->id,
                'status' => 'checked_out',
                'checked_out_at' => now(),
                'condition_out' => $row['condition_out'] ?? $companionItem->condition,
                'fuel_pct_out' => $row['fuel_pct_out'] ?? $companionItem->fuel_pct,
                'usage_hours_out' => $companionItem->usage_hours,
            ]);
            $loanItem = $existing;
        } else {
            $loanItem = LoanItem::query()->create([
                'loan_id' => $loan->id,
                'item_id' => $companionItem->id,
                'companion_of_loan_item_id' => $primaryLoanItem->id,
                'quantity' => 1,
                'status' => 'checked_out',
                'checked_out_at' => now(),
                'condition_out' => $row['condition_out'] ?? $companionItem->condition,
                'fuel_pct_out' => $row['fuel_pct_out'] ?? $companionItem->fuel_pct,
                'usage_hours_out' => $companionItem->usage_hours,
            ]);
        }

        $this->maintenance->recordCheckout($companionItem);
        if ($checkedOut) {
            $companionItem->update(['custom_status_id' => $checkedOut->id]);
        }

        unset($loanItem);
    }

    /**
     * @param  Collection<int, LoanItem>  $primaryByItemId
     * @param  array<string, mixed>  $row
     */
    protected function attachConsumableEstimate(Loan $loan, array $row, Collection $primaryByItemId): void
    {
        $sku = Item::query()->findOrFail($row['item_id']);
        if (! $sku->is_consumable) {
            throw ValidationException::withMessages([
                'consumables' => $sku->displayName().' is not a consumable SKU.',
            ]);
        }

        $qty = (float) ($row['qty_estimated'] ?? 0);
        if ($qty <= 0) {
            throw ValidationException::withMessages(['consumables' => 'Estimated quantity must be greater than zero.']);
        }

        $companionOf = null;
        if (! empty($row['companion_of_item_id'])) {
            $primary = $primaryByItemId->get((int) $row['companion_of_item_id']);
            $companionOf = $primary?->id;
        }

        LoanConsumableIssue::query()->create([
            'loan_id' => $loan->id,
            'item_id' => $sku->id,
            'companion_of_loan_item_id' => $companionOf,
            'qty_estimated' => $qty,
            'status' => 'estimated',
            'notes' => $row['notes'] ?? null,
        ]);
    }
}
