<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\User;
use App\Services\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalkInOrphanReturnTest extends TestCase
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

        return Item::query()->create(array_merge([
            'depot_id' => Depot::query()->value('id'),
            'home_depot_id' => Depot::query()->value('id'),
            'tool_type_id' => ToolType::query()->value('id'),
            'custom_status_id' => $this->availableStatus()->id,
            'asset_tag' => 'WI-'.uniqid(),
            'name' => 'Walk-in test tool',
            'condition' => 'good',
            'is_loanable' => true,
            'is_consumable' => false,
            'qr_token' => $refs->qrToken(),
            'numeric_code' => $refs->numericCode(),
        ], $overrides));
    }

    public function test_borrower_cannot_walk_in_or_orphan_return(): void
    {
        $this->seed();
        $item = $this->makeItem();
        Sanctum::actingAs($this->borrower());

        $this->postJson('/api/loans/walk-in', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertForbidden();

        $this->postJson('/api/loans/orphan-return', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'condition' => 'good',
        ])->assertForbidden();
    }

    public function test_walk_in_creates_checked_out_loan_and_audits(): void
    {
        $this->seed();
        $item = $this->makeItem();
        Sanctum::actingAs($this->depotAdmin());

        $loan = $this->postJson('/api/loans/walk-in', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'due_at' => now()->addDays(2)->toDateTimeString(),
            'condition_out' => 'good',
            'notes' => 'Crew needed it urgently',
        ])->assertCreated()->json('data');

        $this->assertSame('checked_out', $loan['status']);
        $this->assertSame($this->borrower()->id, $loan['borrower_id']);
        $this->assertDatabaseHas('loan_items', [
            'loan_id' => $loan['id'],
            'item_id' => $item->id,
            'status' => 'checked_out',
        ]);
        $this->assertSame(
            CustomStatus::query()->where('slug', 'checked-out')->value('id'),
            $item->fresh()->custom_status_id,
        );
        $this->assertTrue(
            AuditEvent::query()->where('event', 'walk_in_checkout')->where('auditable_id', $loan['id'])->exists()
        );
    }

    public function test_walk_in_blocked_when_item_already_on_open_loan(): void
    {
        $this->seed();
        $item = $this->makeItem();
        $existing = Loan::query()->create([
            'reference' => 'LN-EXIST',
            'property_id' => Property::query()->value('id'),
            'borrower_id' => $this->borrower()->id,
            'depot_id' => $item->depot_id,
            'status' => 'checked_out',
            'reserved_at' => now(),
            'checked_out_at' => now(),
            'due_at' => now()->addDay(),
            'original_due_at' => now()->addDay(),
        ]);
        LoanItem::query()->create([
            'loan_id' => $existing->id,
            'item_id' => $item->id,
            'status' => 'checked_out',
            'checked_out_at' => now(),
        ]);

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson('/api/loans/walk-in', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['item_id']);
    }

    public function test_orphan_return_creates_loan_closes_and_audits(): void
    {
        $this->seed();
        $item = $this->makeItem(['usage_hours' => 10, 'fuel_pct' => 80]);
        Sanctum::actingAs($this->depotAdmin());

        $loan = $this->postJson('/api/loans/orphan-return', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'condition' => 'fair',
            'fuel_pct' => 40,
            'usage_hours_estimate' => 2,
            'overall_result' => 'pass',
            'notes' => 'Found by bay door',
        ])->assertCreated()->json('data');

        $this->assertSame('closed', $loan['status']);
        $this->assertDatabaseHas('loan_items', [
            'loan_id' => $loan['id'],
            'item_id' => $item->id,
            'status' => 'returned',
        ]);
        $this->assertDatabaseHas('return_inspections', [
            'loan_id' => $loan['id'],
            'item_id' => $item->id,
            'admin_reviewed' => true,
        ]);
        $this->assertSame(
            $this->availableStatus()->id,
            $item->fresh()->custom_status_id,
        );
        $this->assertTrue(
            AuditEvent::query()->where('event', 'orphan_return')->where('auditable_id', $loan['id'])->exists()
        );
    }

    public function test_orphan_return_blocked_when_item_already_checked_out(): void
    {
        $this->seed();
        $item = $this->makeItem();
        $existing = Loan::query()->create([
            'reference' => 'LN-OUT',
            'property_id' => Property::query()->value('id'),
            'borrower_id' => $this->borrower()->id,
            'depot_id' => $item->depot_id,
            'status' => 'checked_out',
            'reserved_at' => now(),
            'checked_out_at' => now(),
            'due_at' => now()->addDay(),
            'original_due_at' => now()->addDay(),
        ]);
        LoanItem::query()->create([
            'loan_id' => $existing->id,
            'item_id' => $item->id,
            'status' => 'checked_out',
            'checked_out_at' => now(),
        ]);

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson('/api/loans/orphan-return', [
            'borrower_id' => $this->borrower()->id,
            'item_id' => $item->id,
            'depot_id' => $item->depot_id,
            'property_id' => Property::query()->value('id'),
            'condition' => 'good',
        ])->assertStatus(422)->assertJsonValidationErrors(['item_id']);
    }

    public function test_checkout_staff_can_search_borrowers(): void
    {
        $this->seed();
        Sanctum::actingAs($this->depotAdmin());

        $this->getJson('/api/loans/borrowers?q=joe')
            ->assertOk()
            ->assertJsonFragment(['email' => 'joe@depotborrow.test']);
    }
}
