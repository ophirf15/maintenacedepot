<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MaintenancePlan;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Closed-loop maintenance: overdue checks, loan/fuel/hour counters, and rolling
 * next-due targets after a work order is completed.
 */
class MaintenancePlanService
{
    public const FUEL_REFUEL_THRESHOLD = 20;

    public function __construct(private ItemStatusService $statuses) {}

    public function isOverdue(MaintenancePlan $plan, ?Item $item = null): bool
    {
        $item ??= $plan->relationLoaded('item') ? $plan->item : $plan->item()->first();

        return match ($plan->trigger_type) {
            'calendar' => $plan->next_due_at !== null && $plan->next_due_at->isPast(),
            'usage_hours' => $item && $plan->next_due_hours !== null
                && (float) $item->usage_hours >= (float) $plan->next_due_hours,
            'loan_count' => $item && $plan->next_due_loans !== null
                && (int) $item->lifetime_loan_count >= (int) $plan->next_due_loans,
            'fuel_cycles' => $item && $plan->next_due_fuel_cycles !== null
                && (int) $item->lifetime_fuel_cycles >= (int) $plan->next_due_fuel_cycles,
            default => false,
        };
    }

    /** Active overdue plans for an item, split into blocking vs warn-only. */
    public function overduePlansFor(Item $item): Collection
    {
        return MaintenancePlan::query()
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->with('maintenanceType')
            ->get()
            ->filter(fn (MaintenancePlan $plan) => $this->isOverdue($plan, $item))
            ->values();
    }

    public function summarizeOverdue(Item $item): array
    {
        $overdue = $this->overduePlansFor($item);
        $blocking = $overdue->where('blocks_checkout_when_overdue', true)->values();
        $warnings = $overdue->where('blocks_checkout_when_overdue', false)->values();

        return [
            'overdue' => $overdue,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'requires_override' => $blocking->isNotEmpty(),
        ];
    }

    public function recordCheckout(Item $item): Item
    {
        $item->update([
            'lifetime_loan_count' => (int) $item->lifetime_loan_count + 1,
        ]);

        return $item->fresh();
    }

    /**
     * Apply usage after return review. Prefer absolute hour-meter reading;
     * otherwise add the trip estimate. Count a fuel cycle when fuel rose ≥ 20%.
     *
     * @return array{hours_delta: float, fuel_cycle: bool, item: Item}
     */
    public function recordUsage(
        Item $item,
        ?float $usageHoursReading,
        ?float $usageHoursEstimate,
        ?float $usageHoursOut,
        ?int $fuelOut,
        ?int $fuelIn,
    ): array {
        $hoursDelta = 0.0;
        $newHours = (float) $item->usage_hours;

        if ($usageHoursReading !== null) {
            $baseline = $usageHoursOut !== null ? (float) $usageHoursOut : (float) $item->usage_hours;
            $hoursDelta = max(0, (float) $usageHoursReading - $baseline);
            $newHours = (float) $usageHoursReading;
        } elseif ($usageHoursEstimate !== null) {
            $hoursDelta = max(0, (float) $usageHoursEstimate);
            $newHours = (float) $item->usage_hours + $hoursDelta;
        }

        $fuelCycle = $fuelOut !== null
            && $fuelIn !== null
            && ((int) $fuelIn - (int) $fuelOut) >= self::FUEL_REFUEL_THRESHOLD;

        $updates = ['usage_hours' => $newHours];
        if ($fuelIn !== null) {
            $updates['fuel_pct'] = $fuelIn;
        }
        if ($fuelCycle) {
            $updates['lifetime_fuel_cycles'] = (int) $item->lifetime_fuel_cycles + 1;
        }

        $item->update($updates);

        return [
            'hours_delta' => $hoursDelta,
            'fuel_cycle' => $fuelCycle,
            'item' => $item->fresh(),
        ];
    }

    public function rollForwardAfterService(MaintenancePlan $plan, Item $item): MaintenancePlan
    {
        $updates = ['last_performed_at' => now()];

        if ($plan->trigger_type === 'calendar' && $plan->interval_days) {
            $updates['next_due_at'] = now()->addDays((int) $plan->interval_days);
        }

        if ($plan->trigger_type === 'usage_hours' && $plan->interval_hours) {
            $updates['next_due_hours'] = (float) $item->usage_hours + (float) $plan->interval_hours;
        }

        if ($plan->trigger_type === 'loan_count' && $plan->interval_loans) {
            $updates['next_due_loans'] = (int) $item->lifetime_loan_count + (int) $plan->interval_loans;
        }

        if ($plan->trigger_type === 'fuel_cycles' && $plan->interval_fuel_cycles) {
            $updates['next_due_fuel_cycles'] = (int) $item->lifetime_fuel_cycles + (int) $plan->interval_fuel_cycles;
        }

        $plan->update($updates);

        return $plan->fresh(['item', 'maintenanceType']);
    }

    /** Seed absolute next-due targets when a plan is first created without them. */
    public function initializeNextDue(MaintenancePlan $plan, ?Item $item = null): MaintenancePlan
    {
        $item ??= $plan->item_id
            ? ($plan->relationLoaded('item') ? $plan->item : Item::query()->find($plan->item_id))
            : null;

        $updates = [];

        if ($plan->trigger_type === 'calendar' && $plan->interval_days && ! $plan->next_due_at) {
            $updates['next_due_at'] = now()->addDays((int) $plan->interval_days);
        }

        if ($plan->trigger_type === 'usage_hours' && $plan->interval_hours && $plan->next_due_hours === null && $item) {
            $updates['next_due_hours'] = (float) $item->usage_hours + (float) $plan->interval_hours;
        }

        if ($plan->trigger_type === 'loan_count' && $plan->interval_loans && $plan->next_due_loans === null && $item) {
            $updates['next_due_loans'] = (int) $item->lifetime_loan_count + (int) $plan->interval_loans;
        }

        if ($plan->trigger_type === 'fuel_cycles' && $plan->interval_fuel_cycles && $plan->next_due_fuel_cycles === null && $item) {
            $updates['next_due_fuel_cycles'] = (int) $item->lifetime_fuel_cycles + (int) $plan->interval_fuel_cycles;
        }

        if ($updates) {
            $plan->update($updates);
        }

        return $plan->fresh();
    }

    public function applyDowntimeForWorkOrder(WorkOrder $workOrder): void
    {
        $type = $workOrder->relationLoaded('maintenanceType')
            ? $workOrder->maintenanceType
            : \App\Models\MaintenanceType::query()->find($workOrder->maintenance_type_id);

        $planType = $workOrder->maintenancePlan?->maintenanceType;
        $requires = (bool) (($type?->requires_downtime) || ($planType?->requires_downtime));

        if (! $requires || ! $workOrder->item_id) {
            return;
        }

        $item = $workOrder->item ?: Item::query()->find($workOrder->item_id);
        if ($item) {
            $this->statuses->sendToMaintenance($item, 'Work order '.$workOrder->reference);
        }
    }

    public function restoreAfterWorkOrder(WorkOrder $workOrder): void
    {
        if (! $workOrder->item_id) {
            return;
        }

        $item = $workOrder->item ?: Item::query()->find($workOrder->item_id);
        if (! $item) {
            return;
        }

        $openOosTickets = Ticket::query()
            ->where('item_id', $item->id)
            ->where('takes_out_of_service', true)
            ->whereIn('status', ['open', 'in_progress'])
            ->exists();

        $openDowntimeOrders = WorkOrder::query()
            ->where('item_id', $item->id)
            ->where('id', '!=', $workOrder->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('maintenanceType', fn ($q) => $q->where('requires_downtime', true))
            ->exists();

        if ($openOosTickets || $openDowntimeOrders) {
            return;
        }

        $this->statuses->restoreToService($item, 'Work order '.$workOrder->reference.' completed');
    }
}
