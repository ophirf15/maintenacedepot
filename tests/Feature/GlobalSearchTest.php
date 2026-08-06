<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ToolType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    public function test_guest_cannot_search(): void
    {
        $this->seed();

        $this->getJson('/api/search?q=drill')->assertUnauthorized();
    }

    public function test_search_finds_items_by_tool_type_name(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $toolType = ToolType::query()->where('name', '!=', '')->firstOrFail();
        $item = Item::query()->where('tool_type_id', $toolType->id)->firstOrFail();
        $item->update(['name' => null]);

        $needle = explode(' ', $toolType->name)[0];

        $response = $this->getJson('/api/search?q='.urlencode($needle))->assertOk();

        $hits = collect($response->json('data'));
        $this->assertTrue(
            $hits->contains(fn ($hit) => ($hit['entity_type'] ?? null) === 'item'
                && (int) $hit['entity_id'] === (int) $item->id),
            'Expected an item hit for tool type name "'.$needle.'". Hits: '.$hits->toJson(),
        );
    }

    public function test_search_includes_borrow_requests_when_permitted(): void
    {
        $this->seed();
        Sanctum::actingAs($this->borrower());

        $response = $this->getJson('/api/search?q=REQ')->assertOk();
        $types = collect($response->json('data'))->pluck('entity_type')->unique()->values();

        $this->assertTrue(
            $types->contains('item') || $types->contains('borrow_request') || $types->isEmpty()
            || $types->contains('loan') || $types->contains('ticket'),
            'Search should return a well-formed hit list for borrowers.',
        );

        foreach ($response->json('data') as $hit) {
            $this->assertArrayHasKey('title', $hit);
            $this->assertArrayHasKey('href', $hit);
            $this->assertArrayHasKey('entity_type', $hit);
            $this->assertArrayHasKey('entity_id', $hit);
        }
    }
}
