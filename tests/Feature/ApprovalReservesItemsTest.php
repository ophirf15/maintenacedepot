<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalReservesItemsTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    private function depotAdmin(): User
    {
        return User::query()->where('email', 'mike@depotborrow.test')->firstOrFail();
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

    private function submitRequest(array $lines): int
    {
        Sanctum::actingAs($this->borrower());

        return $this->postJson('/api/borrow-requests', [
            'property_id' => Property::query()->value('id'),
            'pickup_depot_id' => Depot::query()->value('id'),
            'needed_from' => now()->addDay()->toDateTimeString(),
            'needed_until' => now()->addDays(3)->toDateTimeString(),
            'submit' => true,
            'lines' => $lines,
        ])->assertCreated()->json('data.id');
    }

    public function test_approval_reserves_allocated_units_and_removes_them_from_availability(): void
    {
        $this->seed();

        $item = $this->freeItem();

        $requestId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());

        // Mirror what the approval screen posts: echoed dates, no manual item ids,
        // and no "finalize immediately" override.
        $this->postJson("/api/borrow-requests/{$requestId}/approve", [
            'needed_from' => now()->addDay()->format('Y-m-d\TH:i'),
            'needed_until' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'lines' => [],
        ])->assertOk()->assertJsonPath('data.status', 'reserved');

        $borrowRequest = BorrowRequest::query()->with(['loan.items'])->findOrFail($requestId);

        $this->assertNotNull($borrowRequest->loan, 'Approval must open a loan.');
        $this->assertSame(1, $borrowRequest->loan->items->count());
        $this->assertSame($item->id, $borrowRequest->loan->items->first()->item_id);

        $reserved = $item->fresh()->load('status');
        $this->assertSame('reserved', $reserved->status->slug);
        $this->assertFalse($reserved->isAvailableForBorrow());
    }

    public function test_tool_type_lines_are_auto_assigned_to_a_free_unit(): void
    {
        $this->seed();

        $toolTypeId = $this->freeItem()->tool_type_id;

        $requestId = $this->submitRequest([
            ['request_mode' => 'tool_type', 'tool_type_id' => $toolTypeId, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/borrow-requests/{$requestId}/approve", ['lines' => []])
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved');

        $line = BorrowRequest::query()->findOrFail($requestId)->lines()->firstOrFail();

        $this->assertNotNull($line->allocated_item_id, 'Tool-type lines must be auto-assigned.');
        $this->assertSame('reserved', Item::query()->findOrFail($line->allocated_item_id)->status->slug);
    }

    public function test_approval_screen_receives_allocation_suggestions_instead_of_raw_ids(): void
    {
        $this->seed();

        $item = $this->freeItem();

        $requestId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());

        $payload = $this->getJson("/api/borrow-requests/{$requestId}")->assertOk()->json('data');

        $this->assertSame($item->id, $payload['allocation'][0]['suggested_item_id']);
        $this->assertTrue($payload['allocation'][0]['requested_is_available']);
        $this->assertNotEmpty($payload['allocation'][0]['candidates']);
        $this->assertStringContainsString('requested', $payload['summary']);
        $this->assertStringContainsString($item->displayName(), $payload['summary']);
    }

    public function test_reserved_units_are_not_offered_to_the_next_borrower(): void
    {
        $this->seed();

        $toolTypeId = $this->freeItem()->tool_type_id;

        $countFor = function (int $toolTypeId): int {
            Sanctum::actingAs($this->borrower());
            $data = $this->getJson('/api/catalog/categories')->assertOk()->json('data');

            return collect($data)
                ->flatMap(fn ($category) => $category['tool_types'])
                ->firstWhere('id', $toolTypeId)['available_count'];
        };

        $before = $countFor($toolTypeId);

        $requestId = $this->submitRequest([
            ['request_mode' => 'tool_type', 'tool_type_id' => $toolTypeId, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson("/api/borrow-requests/{$requestId}/approve", ['lines' => []])->assertOk();

        $this->assertSame($before - 1, $countFor($toolTypeId), 'Approved units must leave circulation.');
    }

    public function test_dates_echoed_back_from_another_timezone_are_not_treated_as_changes(): void
    {
        $this->seed();

        $toolTypeId = $this->freeItem()->tool_type_id;

        $requestId = $this->submitRequest([
            ['request_mode' => 'tool_type', 'tool_type_id' => $toolTypeId, 'quantity' => 1],
        ]);

        $borrowRequest = BorrowRequest::query()->findOrFail($requestId);

        // A browser west of UTC re-sends the untouched dates as its own local
        // instants. Same moment in time, so approval must not need re-acceptance.
        Sanctum::actingAs($this->depotAdmin());

        $this->postJson("/api/borrow-requests/{$requestId}/approve", [
            'needed_from' => $borrowRequest->needed_from->copy()->setTimezone('America/Los_Angeles')->toIso8601String(),
            'needed_until' => $borrowRequest->needed_until->copy()->setTimezone('America/Los_Angeles')->toIso8601String(),
            'lines' => [],
        ])->assertOk()->assertJsonPath('data.status', 'reserved');
    }

    public function test_dashboard_counts_match_the_lists_they_link_to(): void
    {
        $this->seed();

        $item = $this->freeItem();

        // One request the depot still has to answer, plus one parked with the
        // borrower after a substitution.
        $waitingId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);
        $substitutedId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);
        $this->submitRequest([
            ['request_mode' => 'tool_type', 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson("/api/borrow-requests/{$waitingId}/approve", ['lines' => []])->assertOk();
        $this->postJson("/api/borrow-requests/{$substitutedId}/approve", ['lines' => []])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_modification_accept');

        $stats = $this->getJson('/api/dashboard/stats')->assertOk()->json('data');

        foreach (['pending_requests' => 'submitted', 'awaiting_borrower_requests' => 'pending_modification_accept'] as $key => $status) {
            $listed = $this->getJson("/api/borrow-requests?status={$status}")->assertOk()->json('data.data');

            $this->assertSame(
                count($listed),
                $stats[$key],
                "Dashboard \"{$key}\" must match the {$status} list it links to."
            );
        }

        // Guard against both numbers collapsing to zero and passing vacuously.
        $this->assertGreaterThan(0, $stats['pending_requests']);
        $this->assertGreaterThan(0, $stats['awaiting_borrower_requests']);
    }

    public function test_unavailable_specific_unit_is_substituted_and_flagged_as_a_modification(): void
    {
        $this->seed();

        $item = $this->freeItem();

        // Same unit requested twice over the same window by two separate requests.
        $firstId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);
        $secondId = $this->submitRequest([
            ['request_mode' => 'specific_item', 'item_id' => $item->id, 'tool_type_id' => $item->tool_type_id, 'quantity' => 1],
        ]);

        Sanctum::actingAs($this->depotAdmin());
        $this->postJson("/api/borrow-requests/{$firstId}/approve", ['lines' => []])->assertOk();

        $this->postJson("/api/borrow-requests/{$secondId}/approve", ['lines' => []])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_modification_accept');

        $line = BorrowRequest::query()->findOrFail($secondId)->lines()->firstOrFail();

        $this->assertNotNull($line->allocated_item_id);
        $this->assertNotSame($item->id, $line->allocated_item_id, 'A different free unit should be offered.');
    }
}
