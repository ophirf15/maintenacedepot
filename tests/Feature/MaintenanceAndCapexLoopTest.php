<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Loan;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CapexExportService;
use App\Services\MaintenancePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceAndCapexLoopTest extends TestCase
{
    use RefreshDatabase;

    private function depotAdmin(): User
    {
        return User::query()->where('email', 'mike@depotborrow.test')->firstOrFail();
    }

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    private function freeItem(): Item
    {
        $availableIds = CustomStatus::query()->where('availability_effect', 'available')->pluck('id');

        return Item::query()
            ->whereIn('custom_status_id', $availableIds)
            ->where('is_loanable', true)
            ->whereDoesntHave('loanItems', fn ($q) => $q->whereIn('status', ['reserved', 'checked_out']))
            ->firstOrFail();
    }

    private function reserveLoan(Item $item): Loan
    {
        Sanctum::actingAs($this->borrower());

        $requestId = $this->postJson('/api/borrow-requests', [
            'property_id' => Property::query()->value('id'),
            'pickup_depot_id' => Depot::query()->value('id'),
            'needed_from' => now()->addDay()->toDateTimeString(),
            'needed_until' => now()->addDays(3)->toDateTimeString(),
            'submit' => true,
            'lines' => [
                ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.0.id');

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson("/api/borrow-requests/{$requestId}/approve", ['lines' => []])->assertOk();

        return BorrowRequest::query()->findOrFail($requestId)->loan;
    }

    public function test_blocking_overdue_requires_override_and_qr_mismatch_fails(): void
    {
        $this->seed();

        $item = $this->freeItem();
        $type = MaintenanceType::query()->firstOrFail();

        MaintenancePlan::query()->create([
            'item_id' => $item->id,
            'maintenance_type_id' => $type->id,
            'name' => 'Blocked oil',
            'trigger_type' => 'usage_hours',
            'interval_hours' => 10,
            'next_due_hours' => 0,
            'blocks_checkout_when_overdue' => true,
            'is_active' => true,
        ]);

        $loan = $this->reserveLoan($item);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $item->id]],
        ])->assertStatus(422);

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $item->id, 'qr_token' => 'WRONG']],
            'maintenance_override' => true,
            'maintenance_override_reason' => 'Service booked',
        ])->assertStatus(422);

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $item->id, 'qr_token' => $item->qr_token]],
            'maintenance_override' => true,
            'maintenance_override_reason' => 'Service booked',
        ])->assertOk()->assertJsonPath('data.status', 'checked_out');

        $this->assertSame(1, $item->fresh()->lifetime_loan_count);
    }

    public function test_meter_reading_preferred_over_estimate_and_fuel_cycle_increments(): void
    {
        $this->seed();

        $item = $this->freeItem();
        $item->update(['usage_hours' => 100, 'fuel_pct' => 20, 'lifetime_fuel_cycles' => 0]);

        $loan = $this->reserveLoan($item);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $item->id, 'fuel_pct_out' => 20]],
            'maintenance_override' => true,
            'maintenance_override_reason' => 'ok',
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/self-return", [
            'items' => [[
                'item_id' => $item->id,
                'fuel_pct' => 80,
                'usage_hours_reading' => 108,
                'usage_hours_estimate' => 99,
                'condition' => 'good',
            ]],
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/review-return", [
            'items' => [[
                'item_id' => $item->id,
                'overall_result' => 'pass',
                'fuel_pct' => 80,
                'usage_hours_reading' => 108,
                'condition' => 'good',
            ]],
        ])->assertOk();

        $fresh = $item->fresh();
        $this->assertEquals(108.0, (float) $fresh->usage_hours);
        $this->assertSame(1, (int) $fresh->lifetime_fuel_cycles);
    }

    public function test_inspect_damage_creates_ticket_and_offline_scan_resolves_loan_by_qr(): void
    {
        $this->seed();

        $item = $this->freeItem();
        $loan = $this->reserveLoan($item);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $item->id]],
            'maintenance_override' => true,
            'maintenance_override_reason' => 'ok',
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/self-return", [
            'items' => [['item_id' => $item->id, 'condition' => 'poor', 'fuel_pct' => 40]],
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/review-return", [
            'items' => [[
                'item_id' => $item->id,
                'overall_result' => 'fail',
                'condition' => 'poor',
                'fuel_pct' => 40,
                'take_out_of_service' => true,
                'damage_description' => 'Hose leak',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('tickets', [
            'item_id' => $item->id,
            'takes_out_of_service' => 1,
        ]);

        $item2 = Item::query()
            ->where('id', '!=', $item->id)
            ->where('is_loanable', true)
            ->whereIn('custom_status_id', CustomStatus::query()->where('availability_effect', 'available')->pluck('id'))
            ->whereDoesntHave('loanItems', fn ($q) => $q->whereIn('status', ['reserved', 'checked_out']))
            ->firstOrFail();

        $loan2 = $this->reserveLoan($item2);
        Sanctum::actingAs($this->depotAdmin());

        $result = $this->postJson('/api/loans/sync-offline', [
            'events' => [[
                'client_uuid' => 'test-offline-1',
                'action' => 'checkout',
                'qr_token' => $item2->qr_token,
                'payload' => ['maintenance_override' => true, 'maintenance_override_reason' => 'offline'],
            ]],
        ])->assertOk()->json('data.0');

        $this->assertSame('synced', $result['status']);
        $this->assertSame($loan2->id, $result['loan_id']);
        $this->assertSame('checked_out', $loan2->fresh()->status);
    }

    public function test_work_order_complete_rolls_next_due_for_all_triggers(): void
    {
        $this->seed();

        $item = $this->freeItem();
        $item->update(['usage_hours' => 40, 'lifetime_loan_count' => 3, 'lifetime_fuel_cycles' => 2]);
        $type = MaintenanceType::query()->firstOrFail();
        $service = app(MaintenancePlanService::class);

        $hourPlan = MaintenancePlan::query()->create([
            'item_id' => $item->id,
            'maintenance_type_id' => $type->id,
            'name' => 'Hours',
            'trigger_type' => 'usage_hours',
            'interval_hours' => 25,
            'next_due_hours' => 40,
            'is_active' => true,
        ]);
        $loanPlan = MaintenancePlan::query()->create([
            'item_id' => $item->id,
            'maintenance_type_id' => $type->id,
            'name' => 'Loans',
            'trigger_type' => 'loan_count',
            'interval_loans' => 5,
            'next_due_loans' => 3,
            'is_active' => true,
        ]);
        $fuelPlan = MaintenancePlan::query()->create([
            'item_id' => $item->id,
            'maintenance_type_id' => $type->id,
            'name' => 'Fuel',
            'trigger_type' => 'fuel_cycles',
            'interval_fuel_cycles' => 4,
            'next_due_fuel_cycles' => 2,
            'is_active' => true,
        ]);
        $calPlan = MaintenancePlan::query()->create([
            'item_id' => $item->id,
            'maintenance_type_id' => $type->id,
            'name' => 'Calendar',
            'trigger_type' => 'calendar',
            'interval_days' => 30,
            'next_due_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->assertTrue($service->isOverdue($hourPlan, $item));
        $this->assertTrue($service->isOverdue($loanPlan, $item));
        $this->assertTrue($service->isOverdue($fuelPlan, $item));
        $this->assertTrue($service->isOverdue($calPlan, $item));

        Sanctum::actingAs($this->depotAdmin());

        foreach ([$hourPlan, $loanPlan, $fuelPlan, $calPlan] as $plan) {
            $wo = WorkOrder::query()->create([
                'reference' => 'WO-TEST-'.$plan->id,
                'item_id' => $item->id,
                'maintenance_type_id' => $type->id,
                'maintenance_plan_id' => $plan->id,
                'title' => 'Service '.$plan->name,
                'status' => 'open',
                'priority' => 'normal',
            ]);

            $this->postJson("/api/maintenance/work-orders/{$wo->id}/complete", [
                'total_cost' => 25,
                'completion_notes' => 'Done',
            ])->assertOk();
        }

        $this->assertEquals(65.0, (float) $hourPlan->fresh()->next_due_hours);
        $this->assertSame(8, (int) $loanPlan->fresh()->next_due_loans);
        $this->assertSame(6, (int) $fuelPlan->fresh()->next_due_fuel_cycles);
        $this->assertTrue($calPlan->fresh()->next_due_at->isFuture());
        $this->assertFalse($service->isOverdue($hourPlan->fresh(), $item->fresh()));
    }

    public function test_capex_pulls_year_for_eol_condition_and_repair_spend(): void
    {
        $this->seed();

        $item = $this->freeItem();
        $item->update([
            'purchase_date' => now()->subYears(2)->toDateString(),
            'lifespan_years' => 10,
            'purchase_price' => 1000,
            'replacement_cost' => 1000,
            'salvage_value' => 100,
            'condition' => 'poor',
            'end_of_life_soon' => true,
            'usage_hours' => 0,
        ]);

        WorkOrder::query()->create([
            'reference' => 'WO-CAPEX-1',
            'item_id' => $item->id,
            'title' => 'Big repair',
            'status' => 'completed',
            'priority' => 'normal',
            'total_cost' => 600,
            'completed_at' => now(),
        ]);

        $row = app(CapexExportService::class)->forecast()->firstWhere('asset_tag', $item->asset_tag);

        $this->assertNotNull($row);
        $this->assertSame((int) now()->year, $row['planned_replacement_year']);
        $this->assertContains('eol', $row['suggest_replace_reasons']);
        $this->assertContains('condition', $row['suggest_replace_reasons']);
        $this->assertContains('repair_spend', $row['suggest_replace_reasons']);
        $this->assertEquals(900.0, $row['net_replacement_cost']);
    }
}
