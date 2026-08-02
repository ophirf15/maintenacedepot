<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\WorkOrder;
use App\Services\AuditLogger;
use App\Services\MaintenancePlanService;
use App\Services\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MaintenanceController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private ReferenceGenerator $refs,
        private MaintenancePlanService $plans,
    ) {}

    public function typesIndex(Request $request)
    {
        $query = MaintenanceType::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function typesStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'kind' => 'in:preventive,corrective,inspection',
            'description' => 'nullable|string',
            'requires_downtime' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $type = MaintenanceType::query()->create($data);

        return response()->json(['data' => $type], 201);
    }

    public function plansIndex(Request $request)
    {
        $query = MaintenancePlan::query()->with(['item.toolType', 'toolType', 'maintenanceType']);

        if ($itemId = $request->integer('item_id')) {
            $query->where('item_id', $itemId);
        }

        if ($toolTypeId = $request->integer('tool_type_id')) {
            $query->where('tool_type_id', $toolTypeId);
        }

        $plans = $query->orderBy('next_due_at')->get();

        return response()->json([
            'data' => $plans->map(fn (MaintenancePlan $plan) => [
                ...$plan->toArray(),
                'is_overdue' => $this->plans->isOverdue($plan, $plan->item),
            ]),
        ]);
    }

    public function plansStore(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'tool_type_id' => 'nullable|exists:tool_types,id',
            'maintenance_type_id' => 'required|exists:maintenance_types,id',
            'name' => 'required|string|max:190',
            'trigger_type' => 'required|in:calendar,usage_hours,loan_count,fuel_cycles',
            'interval_days' => 'nullable|integer|min:1',
            'interval_hours' => 'nullable|numeric|min:0.1',
            'interval_loans' => 'nullable|integer|min:1',
            'interval_fuel_cycles' => 'nullable|integer|min:1',
            'next_due_at' => 'nullable|date',
            'next_due_hours' => 'nullable|numeric|min:0',
            'next_due_loans' => 'nullable|integer|min:0',
            'next_due_fuel_cycles' => 'nullable|integer|min:0',
            'blocks_checkout_when_overdue' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $plan = MaintenancePlan::query()->create($data);
        $plan = $this->plans->initializeNextDue($plan->fresh(['item']));

        $this->audit->log('created', $plan, null, $plan->toArray());

        return response()->json(['data' => $plan->load(['item.toolType', 'toolType', 'maintenanceType'])], 201);
    }

    public function plansUpdate(Request $request, MaintenancePlan $plan)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:190',
            'interval_days' => 'nullable|integer|min:1',
            'interval_hours' => 'nullable|numeric|min:0.1',
            'interval_loans' => 'nullable|integer|min:1',
            'interval_fuel_cycles' => 'nullable|integer|min:1',
            'next_due_at' => 'nullable|date',
            'next_due_hours' => 'nullable|numeric|min:0',
            'next_due_loans' => 'nullable|integer|min:0',
            'next_due_fuel_cycles' => 'nullable|integer|min:0',
            'last_performed_at' => 'nullable|date',
            'blocks_checkout_when_overdue' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $old = $plan->toArray();
        $plan->update($data);

        $this->audit->log('updated', $plan, $old, $plan->toArray());

        return response()->json(['data' => $plan->fresh(['item.toolType', 'toolType', 'maintenanceType'])]);
    }

    public function workOrdersIndex(Request $request)
    {
        $query = WorkOrder::query()->with(['item.toolType', 'ticket', 'maintenancePlan', 'maintenanceType']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($itemId = $request->integer('item_id')) {
            $query->where('item_id', $itemId);
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function workOrdersStore(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => 'nullable|exists:tickets,id',
            'item_id' => 'required|exists:items,id',
            'maintenance_type_id' => 'nullable|exists:maintenance_types,id',
            'maintenance_plan_id' => 'nullable|exists:maintenance_plans,id',
            'title' => 'required|string|max:190',
            'description' => 'nullable|string',
            'priority' => 'in:low,normal,high,urgent',
            'is_recurring' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_start_at' => 'nullable|date',
        ]);

        $data['reference'] = $this->refs->make('WO');
        $data['status'] = 'open';

        $workOrder = WorkOrder::query()->create($data);
        $workOrder->load(['item.toolType', 'ticket', 'maintenanceType', 'maintenancePlan.maintenanceType']);

        $this->plans->applyDowntimeForWorkOrder($workOrder);
        $this->audit->log('created', $workOrder, null, $workOrder->toArray());

        return response()->json(['data' => $workOrder->fresh(['item.toolType', 'ticket', 'maintenanceType'])], 201);
    }

    public function workOrdersComplete(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'labour_hours' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'completion_notes' => 'nullable|string',
            'parts_used' => 'nullable|string|max:255',
        ]);

        $data['status'] = 'completed';
        $data['completed_at'] = now();
        $data['completed_by'] = $request->user()->id;
        $data['total_cost'] = $data['total_cost'] ?? ((float) ($data['parts_cost'] ?? 0));

        $old = $workOrder->toArray();
        $workOrder->update($data);
        $workOrder->load(['item', 'maintenancePlan', 'maintenanceType']);

        if ($workOrder->maintenancePlan && $workOrder->item) {
            $this->plans->rollForwardAfterService($workOrder->maintenancePlan, $workOrder->item);
        }

        $this->plans->restoreAfterWorkOrder($workOrder);
        $this->audit->log('completed', $workOrder, $old, $workOrder->toArray());

        return response()->json(['data' => $workOrder->fresh(['item.toolType', 'maintenancePlan', 'maintenanceType'])]);
    }
}
