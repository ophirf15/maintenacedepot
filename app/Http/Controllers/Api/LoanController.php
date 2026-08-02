<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesDepotAccess;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Services\AuditLogger;
use App\Services\CompanionService;
use App\Services\LoanService;
use App\Services\MaintenancePlanService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    use AuthorizesDepotAccess;

    public function __construct(
        private LoanService $loans,
        private MaintenancePlanService $maintenance,
        private CompanionService $companions,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $query = Loan::query()->with([
            'borrower',
            'depot',
            'property',
            'items.item.toolType',
            'items.companionOf',
            'consumableIssues.item',
        ]);

        $user = $request->user();
        if (! $user->can('checkout_items')) {
            $query->where('borrower_id', $user->id);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue')) {
            $query->where('due_at', '<', now())->whereIn('status', ['checked_out', 'return_pending']);
        }

        if ($depotId = $request->integer('depot_id')) {
            $query->where('depot_id', $depotId);
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function show(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        $loan->load([
            'borrower',
            'depot',
            'property',
            'items.item.toolType',
            'items.item.maintenancePlans.maintenanceType',
            'items.inspection',
            'items.companionOf',
            'items.companions.item',
            'consumableIssues.item',
            'extensions',
            'borrowRequest',
        ]);

        $maintenanceByItem = [];
        foreach ($loan->items as $loanItem) {
            if (! $loanItem->item) {
                continue;
            }
            $summary = $this->maintenance->summarizeOverdue($loanItem->item);
            $maintenanceByItem[$loanItem->item_id] = [
                'requires_override' => $summary['requires_override'],
                'blocking' => $summary['blocking']->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'trigger_type' => $p->trigger_type,
                ])->values(),
                'warnings' => $summary['warnings']->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'trigger_type' => $p->trigger_type,
                ])->values(),
            ];
        }

        $missingCompanions = [];
        foreach ($loan->items as $loanItem) {
            if ($loanItem->companion_of_loan_item_id) {
                continue;
            }
            foreach ($loanItem->companions as $companion) {
                if (! in_array($companion->status, ['returned', 'closed'], true)
                    && in_array($loanItem->status, ['returned', 'closed'], true)) {
                    $missingCompanions[] = [
                        'primary' => $loanItem->item?->displayName(),
                        'companion' => $companion->item?->displayName(),
                        'companion_item_id' => $companion->item_id,
                    ];
                }
            }
        }

        return response()->json([
            'data' => array_merge($loan->toArray(), [
                'maintenance_by_item' => $maintenanceByItem,
                'missing_companions' => $missingCompanions,
                'companion_suggestions' => $loan->status === 'reserved'
                    ? $this->companions->suggestionsForLoan($loan)
                    : [],
            ]),
        ]);
    }

    public function companionSuggestions(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        return response()->json([
            'data' => $this->companions->suggestionsForLoan($loan),
        ]);
    }

    public function checkout(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qr_token' => 'nullable|string',
            'items.*.condition_out' => 'nullable|string|max:16',
            'items.*.fuel_pct_out' => 'nullable|integer|min:0|max:100',
            'companions' => 'nullable|array',
            'companions.*.item_id' => 'required_with:companions|exists:items,id',
            'companions.*.companion_of_item_id' => 'required_with:companions|exists:items,id',
            'companions.*.qr_token' => 'nullable|string',
            'companions.*.condition_out' => 'nullable|string|max:16',
            'companions.*.fuel_pct_out' => 'nullable|integer|min:0|max:100',
            'consumables' => 'nullable|array',
            'consumables.*.item_id' => 'required_with:consumables|exists:items,id',
            'consumables.*.companion_of_item_id' => 'nullable|exists:items,id',
            'consumables.*.qty_estimated' => 'required_with:consumables|numeric|gt:0',
            'consumables.*.notes' => 'nullable|string|max:255',
            'maintenance_override' => 'boolean',
            'maintenance_override_reason' => 'nullable|string|max:500',
            'checklist.template_id' => 'nullable|exists:checklist_templates,id',
            'checklist.result' => 'nullable|in:pass,fail',
            'checklist.answers' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $suggestions = $this->companions->suggestionsForLoan($loan);
        $attachedCompanionIds = collect($data['companions'] ?? [])->pluck('item_id')->all();
        $warnings = [];
        foreach ($suggestions as $group) {
            foreach ($group['required_skipped_hints'] as $hint) {
                $hasAny = collect($group['companions'])
                    ->contains(fn ($c) => in_array($c['id'], $attachedCompanionIds, true));
                if (! $hasAny) {
                    $warnings[] = $hint;
                }
            }
        }

        return response()->json([
            'data' => $this->loans->checkout($loan, $request->user(), $data),
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    public function selfReturn(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.condition' => 'nullable|string|max:16',
            'items.*.fuel_pct' => 'nullable|integer|min:0|max:100',
            'items.*.usage_hours_estimate' => 'nullable|numeric|min:0',
            'items.*.usage_hours_reading' => 'nullable|numeric|min:0',
            'items.*.damage_found' => 'boolean',
            'items.*.damage_description' => 'nullable|string',
            'items.*.end_of_life_soon' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        return response()->json(['data' => $this->loans->selfReturn($loan, $request->user(), $data)]);
    }

    public function reviewReturn(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.overall_result' => 'nullable|in:pass,fail',
            'items.*.condition' => 'nullable|string|max:16',
            'items.*.fuel_pct' => 'nullable|integer|min:0|max:100',
            'items.*.usage_hours_estimate' => 'nullable|numeric|min:0',
            'items.*.usage_hours_reading' => 'nullable|numeric|min:0',
            'items.*.damage_found' => 'boolean',
            'items.*.damage_description' => 'nullable|string',
            'items.*.take_out_of_service' => 'boolean',
            'items.*.severity' => 'nullable|in:low,medium,high,critical',
            'items.*.end_of_life_soon' => 'boolean',
            'items.*.lifespan_years' => 'nullable|integer|min:1|max:100',
            'items.*.notes' => 'nullable|string',
            'consumables' => 'nullable|array',
            'consumables.*.id' => 'required_with:consumables|exists:loan_consumable_issues,id',
            'consumables.*.qty_used' => 'nullable|numeric|min:0',
            'consumables.*.allow_negative' => 'boolean',
            'consumables.*.notes' => 'nullable|string|max:255',
        ]);

        return response()->json(['data' => $this->loans->reviewReturn($loan, $request->user(), $data)]);
    }

    public function requestExtension(Request $request, Loan $loan)
    {
        $this->assertCanAccessLoan($request->user(), $loan);

        $data = $request->validate([
            'requested_due_at' => 'required|date|after:now',
            'reason' => 'nullable|string|max:255',
        ]);

        $extension = $this->loans->requestExtension($loan, $request->user(), $data);
        $this->audit->log('extension_requested', $loan, null, $extension->toArray());

        return response()->json(['data' => $extension], 201);
    }

    public function decideExtension(Request $request, LoanExtension $extension)
    {
        $data = $request->validate([
            'approve' => 'required|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $old = $extension->toArray();
        $decided = $this->loans->decideExtension($extension, $request->user(), $data['approve'], $data['note'] ?? null);
        $this->audit->log(
            $data['approve'] ? 'extension_approved' : 'extension_rejected',
            $extension->loan,
            $old,
            $decided->toArray(),
        );

        return response()->json(['data' => $decided]);
    }

    public function syncOffline(Request $request)
    {
        $data = $request->validate([
            'events' => 'required|array|min:1',
            'events.*.client_uuid' => 'required|string',
            'events.*.action' => 'required|in:checkout,return',
            'events.*.qr_token' => 'required|string',
            'events.*.loan_id' => 'nullable|exists:loans,id',
            'events.*.payload' => 'nullable|array',
            'events.*.scanned_at' => 'nullable|date',
        ]);

        $results = collect($data['events'])
            ->map(fn (array $event) => $this->loans->syncOfflineScan($request->user(), $event));

        return response()->json(['data' => $results]);
    }
}
