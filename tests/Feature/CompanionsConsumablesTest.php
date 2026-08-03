<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\ItemLink;
use App\Models\Loan;
use App\Models\LoanConsumableIssue;
use App\Models\LoanItem;
use App\Models\Property;
use App\Models\StockMovement;
use App\Models\ToolType;
use App\Models\ToolTypeLink;
use App\Models\User;
use App\Services\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanionsConsumablesTest extends TestCase
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

    private function availableStatus(): CustomStatus
    {
        return CustomStatus::query()->where('slug', 'available')->firstOrFail();
    }

    private function makeItem(array $overrides = []): Item
    {
        $refs = app(ReferenceGenerator::class);
        $type = ToolType::query()->firstOrFail();
        $depot = Depot::query()->firstOrFail();

        return Item::query()->create(array_merge([
            'depot_id' => $depot->id,
            'home_depot_id' => $depot->id,
            'tool_type_id' => $type->id,
            'custom_status_id' => $this->availableStatus()->id,
            'asset_tag' => 'TEST-'.uniqid(),
            'name' => 'Test item',
            'condition' => 'good',
            'is_loanable' => true,
            'is_consumable' => false,
            'qr_token' => $refs->qrToken(),
            'numeric_code' => $refs->numericCode(),
        ], $overrides));
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

    public function test_companion_attach_pairs_on_checkout_and_skip_does_not_fail(): void
    {
        $this->seed();

        $drill = $this->makeItem(['name' => 'Cordless Drill']);
        $battery = $this->makeItem(['name' => '18V Battery A']);

        ItemLink::query()->create([
            'parent_item_id' => $drill->id,
            'child_item_id' => $battery->id,
            'role' => 'companion',
            'is_required' => true,
        ]);

        $loan = $this->reserveLoan($drill);
        Sanctum::actingAs($this->depotAdmin());

        $skip = $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $drill->id]],
        ]);
        $skip->assertOk();
        $this->assertNotEmpty($skip->json('warnings'));

        // Reset for attach path — reopen a fresh reservation.
        $drill2 = $this->makeItem(['name' => 'Cordless Drill 2']);
        $battery2 = $this->makeItem(['name' => '18V Battery B']);
        ItemLink::query()->create([
            'parent_item_id' => $drill2->id,
            'child_item_id' => $battery2->id,
            'role' => 'companion',
            'is_required' => false,
        ]);
        $loan2 = $this->reserveLoan($drill2);

        $this->postJson("/api/loans/{$loan2->id}/checkout", [
            'items' => [['item_id' => $drill2->id]],
            'companions' => [[
                'item_id' => $battery2->id,
                'companion_of_item_id' => $drill2->id,
                'qr_token' => $battery2->qr_token,
            ]],
        ])->assertOk();

        $batteryLine = LoanItem::query()
            ->where('loan_id', $loan2->id)
            ->where('item_id', $battery2->id)
            ->firstOrFail();
        $drillLine = LoanItem::query()
            ->where('loan_id', $loan2->id)
            ->where('item_id', $drill2->id)
            ->firstOrFail();

        $this->assertSame($drillLine->id, $batteryLine->companion_of_loan_item_id);
        $this->assertSame('checked_out', $batteryLine->status);
    }

    public function test_tool_type_link_suggests_available_companions(): void
    {
        $this->seed();

        $drillType = ToolType::query()->firstOrFail();
        $batteryType = ToolType::query()->where('id', '!=', $drillType->id)->firstOrFail();

        ToolTypeLink::query()->create([
            'parent_tool_type_id' => $drillType->id,
            'child_tool_type_id' => $batteryType->id,
            'role' => 'companion',
            'is_required' => true,
        ]);

        $drill = $this->makeItem(['name' => 'Type-linked drill', 'tool_type_id' => $drillType->id]);
        $battery = $this->makeItem(['name' => 'Pool battery', 'tool_type_id' => $batteryType->id]);

        $loan = $this->reserveLoan($drill);
        Sanctum::actingAs($this->depotAdmin());

        $suggestions = $this->getJson("/api/loans/{$loan->id}/companion-suggestions")
            ->assertOk()
            ->json('data');

        $group = collect($suggestions)->firstWhere('item_id', $drill->id);
        $this->assertNotNull($group);
        $ids = collect($group['companions'])->pluck('id')->all();
        $this->assertContains($battery->id, $ids);
    }

    public function test_consumable_estimate_then_confirm_decrements_stock(): void
    {
        $this->seed();

        $welder = $this->makeItem(['name' => 'MIG Welder']);
        $rods = $this->makeItem([
            'name' => 'Welding Rods',
            'is_consumable' => true,
            'is_loanable' => false,
            'stock_qty' => 100,
            'reorder_point' => 20,
            'stock_unit' => 'lbs',
            'supplier_name' => 'Lincoln Supply',
            'supplier_part_number' => 'ER70S-6',
            'typical_cost' => 4.5,
        ]);

        ItemLink::query()->create([
            'parent_item_id' => $welder->id,
            'child_item_id' => $rods->id,
            'role' => 'consumable',
            'is_required' => false,
        ]);

        $loan = $this->reserveLoan($welder);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $welder->id]],
            'consumables' => [[
                'item_id' => $rods->id,
                'companion_of_item_id' => $welder->id,
                'qty_estimated' => 5,
            ]],
        ])->assertOk();

        $rods->refresh();
        $this->assertSame(100.0, (float) $rods->stock_qty);

        $issue = LoanConsumableIssue::query()->where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame('estimated', $issue->status);

        $this->postJson("/api/loans/{$loan->id}/self-return", [
            'items' => [['item_id' => $welder->id, 'condition' => 'good']],
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/review-return", [
            'items' => [['item_id' => $welder->id, 'overall_result' => 'pass', 'condition' => 'good']],
            'consumables' => [['id' => $issue->id, 'qty_used' => 3]],
        ])->assertOk();

        $rods->refresh();
        $this->assertSame(97.0, (float) $rods->stock_qty);
        $this->assertTrue(StockMovement::query()->where('item_id', $rods->id)->where('reason', 'issue_confirm')->exists());
        $this->assertSame('confirmed', $issue->fresh()->status);
    }

    public function test_restock_adjust_and_low_stock_list(): void
    {
        $this->seed();
        Sanctum::actingAs($this->depotAdmin());

        $sku = $this->makeItem([
            'name' => 'CO2 Cartridges',
            'is_consumable' => true,
            'is_loanable' => false,
            'stock_qty' => 5,
            'reorder_point' => 10,
            'stock_unit' => 'ea',
        ]);

        $this->postJson("/api/items/{$sku->id}/stock/restock", ['qty' => 20, 'notes' => 'Weekly order'])
            ->assertOk()
            ->assertJsonPath('data.stock_qty', '25.00');

        $this->postJson("/api/items/{$sku->id}/stock/adjust", ['qty' => 8])
            ->assertOk()
            ->assertJsonPath('data.stock_qty', '8.00');

        $list = $this->getJson('/api/stock/consumables?low_stock=1')->assertOk()->json('data');
        $rows = $list['data'] ?? $list;
        $this->assertTrue(collect($rows)->contains(fn ($r) => (int) $r['id'] === $sku->id));
    }

    public function test_cannot_attach_consumable_as_companion(): void
    {
        $this->seed();

        $tool = $this->makeItem(['name' => 'Tool']);
        $sku = $this->makeItem([
            'name' => 'Blades',
            'is_consumable' => true,
            'is_loanable' => false,
            'stock_qty' => 50,
        ]);

        $loan = $this->reserveLoan($tool);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $tool->id]],
            'companions' => [[
                'item_id' => $sku->id,
                'companion_of_item_id' => $tool->id,
            ]],
        ])->assertStatus(422);
    }

    public function test_oversell_without_override_fails_on_confirm(): void
    {
        $this->seed();

        $tool = $this->makeItem(['name' => 'Unclogger']);
        $sku = $this->makeItem([
            'name' => 'CO2',
            'is_consumable' => true,
            'is_loanable' => false,
            'stock_qty' => 2,
        ]);

        $loan = $this->reserveLoan($tool);
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $tool->id]],
            'consumables' => [['item_id' => $sku->id, 'qty_estimated' => 5]],
        ])->assertOk();

        $issue = LoanConsumableIssue::query()->where('loan_id', $loan->id)->firstOrFail();

        $this->postJson("/api/loans/{$loan->id}/self-return", [
            'items' => [['item_id' => $tool->id]],
        ])->assertOk();

        $this->postJson("/api/loans/{$loan->id}/review-return", [
            'items' => [['item_id' => $tool->id, 'overall_result' => 'pass']],
            'consumables' => [['id' => $issue->id, 'qty_used' => 5]],
        ])->assertStatus(422);
    }
}
