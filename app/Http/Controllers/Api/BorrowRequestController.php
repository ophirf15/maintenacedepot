<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesDepotAccess;
use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Services\AuditLogger;
use App\Services\BorrowService;
use App\Services\CartCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BorrowRequestController extends Controller
{
    use AuthorizesDepotAccess;

    public function __construct(
        private BorrowService $borrow,
        private CartCheckoutService $checkout,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $query = BorrowRequest::query()->with([
            'property', 'requester', 'onBehalfOf', 'pickupDepot',
            'lines.item.toolType', 'lines.toolType',
        ]);

        $user = $request->user();
        if (! $user->can('approve_requests')) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('on_behalf_of_id', $user->id);
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($propertyId = $request->integer('property_id')) {
            $query->where('property_id', $propertyId);
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'on_behalf_of_id' => 'nullable|exists:users,id',
            // Ignored for correctness — pickup is derived from each tool's depot.
            'pickup_depot_id' => 'nullable|exists:depots,id',
            'priority' => 'in:low,normal,high,urgent',
            'purpose' => 'nullable|string',
            'needed_from' => 'required|date',
            'needed_until' => 'required|date|after:needed_from',
            'expected_dropoff_at' => 'nullable|date',
            'submit' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.request_mode' => 'required|in:specific_item,tool_type',
            'lines.*.item_id' => 'nullable|exists:items,id',
            'lines.*.tool_type_id' => 'nullable|exists:tool_types,id',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.notes' => 'nullable|string|max:255',
        ]);

        if ($data['on_behalf_of_id'] ?? null) {
            if (! $request->user()->can('request_on_behalf')) {
                throw ValidationException::withMessages(['on_behalf_of_id' => 'Not permitted to request on behalf of others.']);
            }
        }

        $result = $this->checkout->checkout($request->user(), $data);

        return response()->json([
            'data' => $result['requests'],
            'split' => $result['split'],
        ], 201);
    }

    public function show(Request $request, BorrowRequest $borrowRequest)
    {
        $this->assertCanAccessBorrowRequest($request->user(), $borrowRequest);

        $borrowRequest->load([
            'lines.item.toolType', 'lines.item.specValues.field',
            'lines.toolType.specFields', 'lines.allocatedItem.toolType',
            'lines.allocatedItem.specValues.field',
            'property', 'requester', 'onBehalfOf', 'pickupDepot', 'loan',
        ]);

        $payload = $borrowRequest->toArray();

        if ($request->user()->can('approve_requests')) {
            $payload['allocation'] = $this->allocationHints($borrowRequest);
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Pre-computed allocation choices so approvers never type raw item IDs.
     */
    private function allocationHints(BorrowRequest $borrowRequest): array
    {
        return $borrowRequest->lines->map(function ($line) use ($borrowRequest) {
            $toolTypeId = $line->tool_type_id ?: $line->item?->tool_type_id;

            $candidates = $this->borrow
                ->availableUnits($toolTypeId, $borrowRequest->pickup_depot_id)
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->displayName(),
                    'asset_tag' => $item->asset_tag,
                    'depot' => $item->depot?->name,
                    'image_url' => $item->image_url,
                    'specs' => $item->specs,
                    'is_available' => true,
                ])
                ->values()
                ->all();

            $requestedIsFree = $line->item_id
                && collect($candidates)->contains(fn ($c) => $c['id'] === $line->item_id);

            if ($line->item_id && ! $requestedIsFree && $line->item) {
                $line->item->loadMissing(['depot', 'specValues.field']);
                array_unshift($candidates, [
                    'id' => $line->item->id,
                    'label' => $line->item->displayName(),
                    'asset_tag' => $line->item->asset_tag,
                    'depot' => $line->item->depot?->name,
                    'image_url' => $line->item->image_url,
                    'specs' => $line->item->specs,
                    'is_available' => false,
                ]);
            }

            return [
                'line_id' => $line->id,
                'requested_item_id' => $line->item_id,
                'requested_is_available' => (bool) $requestedIsFree,
                'suggested_item_id' => $this->borrow->suggestAllocation($line, $borrowRequest->pickup_depot_id)?->id,
                'candidates' => $candidates,
            ];
        })->all();
    }

    public function submit(Request $request, BorrowRequest $borrowRequest)
    {
        $this->assertCanAccessBorrowRequest($request->user(), $borrowRequest);

        if ($borrowRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft requests can be submitted.'], 422);
        }

        return response()->json(['data' => $this->borrow->submit($borrowRequest)]);
    }

    public function approve(Request $request, BorrowRequest $borrowRequest)
    {
        $data = $request->validate([
            'lines' => 'nullable|array',
            'lines.*.id' => 'required_with:lines|exists:borrow_request_lines,id',
            'lines.*.status' => 'nullable|in:allocated,rejected,waitlisted',
            'lines.*.allocated_item_id' => 'nullable|exists:items,id',
            'lines.*.reject_reason' => 'nullable|string|max:255',
            'needed_from' => 'nullable|date',
            'needed_until' => 'nullable|date',
            'approval_note' => 'nullable|string|max:255',
            'modification_note' => 'nullable|string|max:255',
            'force_finalize' => 'boolean',
        ]);

        $result = $this->borrow->approve($borrowRequest, $request->user(), $data);

        return response()->json(['data' => $result]);
    }

    public function acceptModification(Request $request, BorrowRequest $borrowRequest)
    {
        $this->assertCanAccessBorrowRequest($request->user(), $borrowRequest);

        return response()->json(['data' => $this->borrow->acceptModification($borrowRequest, $request->user())]);
    }

    public function rejectModification(Request $request, BorrowRequest $borrowRequest)
    {
        $this->assertCanAccessBorrowRequest($request->user(), $borrowRequest);

        return response()->json(['data' => $this->borrow->rejectModification($borrowRequest, $request->user())]);
    }

    public function cancel(Request $request, BorrowRequest $borrowRequest)
    {
        $this->assertCanAccessBorrowRequest($request->user(), $borrowRequest);

        if (in_array($borrowRequest->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Request cannot be cancelled.'], 422);
        }

        $old = $borrowRequest->toArray();
        $borrowRequest->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->audit->log('cancelled', $borrowRequest, $old, $borrowRequest->toArray());

        return response()->json(['data' => $borrowRequest->fresh()]);
    }
}
