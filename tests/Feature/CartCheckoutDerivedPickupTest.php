<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartCheckoutDerivedPickupTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    private function freeItems(): \Illuminate\Support\Collection
    {
        $availableIds = CustomStatus::query()->where('availability_effect', 'available')->pluck('id');

        return Item::query()
            ->whereIn('custom_status_id', $availableIds)
            ->where('is_loanable', true)
            ->whereDoesntHave('loanItems', fn ($q) => $q->whereIn('status', ['reserved', 'checked_out']))
            ->with('depot')
            ->orderBy('id')
            ->get();
    }

    private function basePayload(array $lines, array $extra = []): array
    {
        return array_merge([
            'property_id' => Property::query()->value('id'),
            'needed_from' => now()->addDay()->toDateTimeString(),
            'needed_until' => now()->addDays(3)->toDateTimeString(),
            'submit' => true,
            'lines' => $lines,
        ], $extra);
    }

    public function test_single_depot_bag_creates_one_request_without_client_pickup(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $item = $this->freeItems()->firstOrFail();

        $response = $this->postJson('/api/borrow-requests', $this->basePayload([
            [
                'request_mode' => 'specific_item',
                'item_id' => $item->id,
                'tool_type_id' => $item->tool_type_id,
                'quantity' => 1,
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('split', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pickup_depot_id', $item->depot_id)
            ->assertJsonPath('data.0.status', 'submitted');
    }

    public function test_items_at_two_depots_split_into_two_requests(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $items = $this->freeItems();
        $depotIds = $items->pluck('depot_id')->unique()->values();
        $this->assertGreaterThanOrEqual(2, $depotIds->count(), 'Seed data needs items at 2+ depots');

        $a = $items->firstWhere('depot_id', $depotIds[0]);
        $b = $items->firstWhere('depot_id', $depotIds[1]);

        $response = $this->postJson('/api/borrow-requests', $this->basePayload([
            [
                'request_mode' => 'specific_item',
                'item_id' => $a->id,
                'tool_type_id' => $a->tool_type_id,
                'quantity' => 1,
            ],
            [
                'request_mode' => 'specific_item',
                'item_id' => $b->id,
                'tool_type_id' => $b->tool_type_id,
                'quantity' => 1,
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('split', true)
            ->assertJsonCount(2, 'data');

        $pickupIds = collect($response->json('data'))->pluck('pickup_depot_id')->sort()->values();
        $this->assertEquals(
            collect([$a->depot_id, $b->depot_id])->sort()->values()->all(),
            $pickupIds->all()
        );

        foreach ($response->json('data') as $request) {
            $this->assertSame('submitted', $request['status']);
            $this->assertCount(1, $request['lines']);
        }

        $createdIds = collect($response->json('data'))->pluck('id');
        $this->assertCount(2, $createdIds->unique());
    }

    public function test_wrong_client_pickup_depot_is_ignored_for_specific_item(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $item = $this->freeItems()->firstOrFail();
        $otherDepot = Depot::query()->where('id', '!=', $item->depot_id)->value('id');
        $this->assertNotNull($otherDepot);

        $this->postJson('/api/borrow-requests', $this->basePayload([
            [
                'request_mode' => 'specific_item',
                'item_id' => $item->id,
                'tool_type_id' => $item->tool_type_id,
                'quantity' => 1,
            ],
        ], [
            'pickup_depot_id' => $otherDepot,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.0.pickup_depot_id', $item->depot_id);
    }

    public function test_tool_type_line_resolves_to_depot_with_most_available_stock(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $availableIds = CustomStatus::query()->where('availability_effect', 'available')->pluck('id');

        $toolType = ToolType::query()
            ->withCount(['items as available_count' => function ($q) use ($availableIds) {
                $q->where('is_loanable', true)
                    ->whereIn('custom_status_id', $availableIds)
                    ->whereDoesntHave('loanItems', fn ($lq) => $lq->whereIn('status', ['reserved', 'checked_out']));
            }])
            ->orderByDesc('available_count')
            ->firstOrFail();

        $this->assertGreaterThan(0, $toolType->available_count);

        $counts = Item::query()
            ->where('tool_type_id', $toolType->id)
            ->where('is_loanable', true)
            ->whereIn('custom_status_id', $availableIds)
            ->whereDoesntHave('loanItems', fn ($q) => $q->whereIn('status', ['reserved', 'checked_out']))
            ->selectRaw('depot_id, count(*) as c')
            ->groupBy('depot_id')
            ->orderByDesc('c')
            ->orderBy('depot_id')
            ->get();

        $expectedDepotId = (int) $counts->first()->depot_id;

        $this->postJson('/api/borrow-requests', $this->basePayload([
            [
                'request_mode' => 'tool_type',
                'tool_type_id' => $toolType->id,
                'quantity' => 1,
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.0.pickup_depot_id', $expectedDepotId);
    }

    public function test_tool_type_with_no_available_stock_returns_422(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $toolType = ToolType::query()->create([
            'category_id' => ToolType::query()->value('category_id'),
            'name' => 'Ghost Tool With No Stock',
            'slug' => 'ghost-tool-no-stock-'.uniqid(),
            'is_active' => true,
        ]);

        $this->postJson('/api/borrow-requests', $this->basePayload([
            [
                'request_mode' => 'tool_type',
                'tool_type_id' => $toolType->id,
                'quantity' => 1,
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines']);
    }
}
