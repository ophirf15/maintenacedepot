<?php

namespace Tests\Feature;

use App\Models\Depot;
use App\Models\Item;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    public function test_guest_cannot_access_cart(): void
    {
        $this->seed();

        $this->getJson('/api/cart')->assertUnauthorized();
        $this->putJson('/api/cart', ['lines' => []])->assertUnauthorized();
        $this->deleteJson('/api/cart')->assertUnauthorized();
    }

    public function test_get_returns_empty_cart_for_borrower(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.property_id', null)
            ->assertJsonPath('data.pickup_depot_id', null)
            ->assertJsonPath('data.needed_from', null)
            ->assertJsonPath('data.needed_until', null)
            ->assertJsonPath('data.purpose', null)
            ->assertJsonPath('data.priority', 'normal');
    }

    public function test_put_persists_lines_and_meta_and_get_returns_them(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $toolType = ToolType::query()->firstOrFail();
        $item = Item::query()->where('tool_type_id', $toolType->id)->first();
        $propertyId = Property::query()->value('id');
        $depotId = Depot::query()->value('id');
        $from = now()->addDay()->toIso8601String();
        $until = now()->addDays(3)->toIso8601String();

        $payload = [
            'property_id' => $propertyId,
            'pickup_depot_id' => $depotId,
            'needed_from' => $from,
            'needed_until' => $until,
            'purpose' => 'Garage cleaning',
            'priority' => 'high',
            'lines' => [
                [
                    'request_mode' => 'tool_type',
                    'tool_type_id' => $toolType->id,
                    'quantity' => 2,
                    'notes' => '',
                    'label' => $toolType->name,
                    'icon' => 'package',
                    '_key' => 'type-'.$toolType->id,
                ],
                [
                    'request_mode' => 'specific_item',
                    'item_id' => $item?->id,
                    'tool_type_id' => $toolType->id,
                    'quantity' => 1,
                    'notes' => 'Preferred unit',
                    'label' => 'Unit A',
                    'icon' => 'wrench',
                    '_key' => 'item-'.($item?->id ?? 0),
                ],
            ],
        ];

        if (! $item) {
            array_pop($payload['lines']);
        }

        $this->putJson('/api/cart', $payload)
            ->assertOk()
            ->assertJsonPath('data.purpose', 'Garage cleaning')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.property_id', $propertyId)
            ->assertJsonPath('data.pickup_depot_id', $depotId)
            ->assertJsonPath('data.lines.0.tool_type_id', $toolType->id)
            ->assertJsonPath('data.lines.0.quantity', 2);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.purpose', 'Garage cleaning')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.lines.0.label', $toolType->name);
    }

    public function test_cart_is_shared_across_sessions_for_same_user(): void
    {
        $this->seed();
        $user = $this->borrower();
        $toolType = ToolType::query()->firstOrFail();

        Sanctum::actingAs($user);
        $this->putJson('/api/cart', [
            'lines' => [
                [
                    'request_mode' => 'tool_type',
                    'tool_type_id' => $toolType->id,
                    'quantity' => 1,
                    'label' => $toolType->name,
                    '_key' => 'type-'.$toolType->id,
                ],
            ],
            'priority' => 'urgent',
            'purpose' => 'From desktop',
        ])->assertOk();

        // Simulate another device / fresh Sanctum session for the same account.
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.purpose', 'From desktop')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.lines.0.tool_type_id', $toolType->id);
    }

    public function test_delete_clears_cart(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $toolType = ToolType::query()->firstOrFail();

        $this->putJson('/api/cart', [
            'lines' => [
                [
                    'request_mode' => 'tool_type',
                    'tool_type_id' => $toolType->id,
                    'quantity' => 1,
                    'label' => $toolType->name,
                ],
            ],
            'purpose' => 'Will clear',
            'priority' => 'high',
            'property_id' => Property::query()->value('id'),
        ])->assertOk();

        $this->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.purpose', null)
            ->assertJsonPath('data.property_id', null)
            ->assertJsonPath('data.priority', 'normal');

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.purpose', null);
    }

    public function test_put_rejects_invalid_lines(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $this->putJson('/api/cart', [
            'lines' => [
                [
                    'request_mode' => 'not_a_mode',
                    'quantity' => 1,
                ],
            ],
        ])->assertStatus(422);

        $this->putJson('/api/cart', [
            'lines' => [
                [
                    'request_mode' => 'tool_type',
                    'tool_type_id' => 999999,
                    'quantity' => 1,
                ],
            ],
        ])->assertStatus(422);

        $this->putJson('/api/cart', [
            'needed_from' => now()->addDays(3)->toIso8601String(),
            'needed_until' => now()->addDay()->toIso8601String(),
            'lines' => [],
        ])->assertStatus(422);
    }

    public function test_carts_are_isolated_per_user(): void
    {
        $this->seed();
        $toolType = ToolType::query()->firstOrFail();

        Sanctum::actingAs($this->borrower());
        $this->putJson('/api/cart', [
            'lines' => [
                [
                    'request_mode' => 'tool_type',
                    'tool_type_id' => $toolType->id,
                    'quantity' => 1,
                    'label' => 'Joe line',
                ],
            ],
            'purpose' => 'Joe cart',
        ])->assertOk();

        $other = User::factory()->create([
            'email' => 'other-borrower@depotborrow.test',
            'is_active' => true,
        ]);
        $other->syncRoles(['borrower']);

        Sanctum::actingAs($other);
        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.purpose', null);
    }
}
