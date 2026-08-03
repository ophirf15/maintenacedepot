<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\User;
use App\Services\ReferenceGenerator;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function seedApp(): void
    {
        $this->seed();
    }

    private function joe(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    private function mike(): User
    {
        return User::query()->where('email', 'mike@depotborrow.test')->firstOrFail();
    }

    private function otherBorrower(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'raj@depotborrow.test'],
            [
                'name' => 'Raj Patel',
                'password' => Hash::make('password'),
                'is_active' => true,
                'default_property_id' => Property::query()->value('id'),
            ]
        );
        $user->syncRoles(['borrower']);

        return $user->fresh();
    }

    private function makeItem(): Item
    {
        $refs = app(ReferenceGenerator::class);

        return Item::query()->create([
            'depot_id' => Depot::query()->value('id'),
            'home_depot_id' => Depot::query()->value('id'),
            'tool_type_id' => ToolType::query()->value('id'),
            'custom_status_id' => CustomStatus::query()->where('slug', 'available')->value('id'),
            'asset_tag' => 'SEC-'.uniqid(),
            'name' => 'Security test item',
            'condition' => 'good',
            'is_loanable' => true,
            'is_consumable' => false,
            'qr_token' => $refs->qrToken(),
            'numeric_code' => $refs->numericCode(),
        ]);
    }

    private function reserveLoanFor(User $borrower, Item $item): Loan
    {
        Sanctum::actingAs($borrower);

        $requestId = $this->postJson('/api/borrow-requests', [
            'property_id' => Property::query()->value('id'),
            'pickup_depot_id' => Depot::query()->value('id'),
            'needed_from' => now()->addDay()->toDateTimeString(),
            'needed_until' => now()->addDays(3)->toDateTimeString(),
            'submit' => true,
            'lines' => [[
                'request_mode' => 'specific_item',
                'item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertCreated()->json('data.0.id');

        Sanctum::actingAs($this->mike());
        $this->postJson("/api/borrow-requests/{$requestId}/approve", [
            'lines' => [[
                'id' => BorrowRequest::query()->findOrFail($requestId)->lines()->value('id'),
                'status' => 'allocated',
                'allocated_item_id' => $item->id,
            ]],
            'force_finalize' => true,
        ])->assertOk();

        return Loan::query()->where('borrow_request_id', $requestId)->firstOrFail();
    }

    public function test_saml_acs_rejects_unsigned_attribute_dumps(): void
    {
        $this->seedApp();
        app(SettingsService::class)->set('saml', 'enabled', true, 'boolean');

        $this->postJson('/api/auth/saml/acs', [
            'email' => 'admin@depotborrow.test',
            'name' => 'Attacker',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['saml']);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'login_failed',
        ]);
    }

    public function test_borrower_cannot_view_another_users_loan(): void
    {
        $this->seedApp();
        $loan = $this->reserveLoanFor($this->joe(), $this->makeItem());

        Sanctum::actingAs($this->otherBorrower());
        $this->getJson("/api/loans/{$loan->id}")->assertForbidden();
        $this->postJson("/api/loans/{$loan->id}/request-extension", [
            'requested_due_at' => now()->addWeek()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_borrower_cannot_view_another_users_borrow_request(): void
    {
        $this->seedApp();
        Sanctum::actingAs($this->joe());

        $requestId = $this->postJson('/api/borrow-requests', [
            'property_id' => Property::query()->value('id'),
            'pickup_depot_id' => Depot::query()->value('id'),
            'needed_from' => now()->addDay()->toDateTimeString(),
            'needed_until' => now()->addDays(2)->toDateTimeString(),
            'submit' => false,
            'lines' => [[
                'request_mode' => 'tool_type',
                'tool_type_id' => ToolType::query()->value('id'),
                'quantity' => 1,
            ]],
        ])->assertCreated()->json('data.0.id');

        Sanctum::actingAs($this->otherBorrower());
        $this->getJson("/api/borrow-requests/{$requestId}")->assertForbidden();
        $this->postJson("/api/borrow-requests/{$requestId}/cancel")->assertForbidden();
    }

    public function test_offline_sync_requires_checkout_permission(): void
    {
        $this->seedApp();
        Sanctum::actingAs($this->joe());

        $this->postJson('/api/loans/sync-offline', [
            'events' => [[
                'client_uuid' => 'sec-offline-1',
                'action' => 'checkout',
                'qr_token' => 'not-a-real-token',
            ]],
        ])->assertForbidden();
    }

    public function test_borrower_ticket_cannot_take_item_out_of_service(): void
    {
        $this->seedApp();
        $item = $this->makeItem();
        $availableId = $item->custom_status_id;

        Sanctum::actingAs($this->joe());
        $ticket = $this->postJson('/api/tickets', [
            'item_id' => $item->id,
            'ticket_type' => 'defect',
            'title' => 'Broken latch',
            'severity' => 'critical',
            'takes_out_of_service' => true,
        ])->assertCreated()->json('data');

        $this->assertFalse((bool) $ticket['takes_out_of_service']);
        $this->assertSame('high', $ticket['severity']);
        $this->assertSame($availableId, $item->fresh()->custom_status_id);
    }

    public function test_tickets_list_is_scoped_to_reporter_for_borrowers(): void
    {
        $this->seedApp();
        Sanctum::actingAs($this->joe());
        $mine = $this->postJson('/api/tickets', [
            'ticket_type' => 'other',
            'title' => 'Mine',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->otherBorrower());
        $theirs = $this->postJson('/api/tickets', [
            'ticket_type' => 'other',
            'title' => 'Theirs',
        ])->assertCreated()->json('data.id');

        $ids = collect($this->getJson('/api/tickets')->assertOk()->json('data.data'))
            ->pluck('id')
            ->all();

        $this->assertContains($theirs, $ids);
        $this->assertNotContains($mine, $ids);

        $this->getJson("/api/tickets/{$mine}")->assertForbidden();
    }

    public function test_logout_writes_audit_event(): void
    {
        $this->seedApp();
        Sanctum::actingAs($this->joe());

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertTrue(
            AuditEvent::query()->where('event', 'logout')->where('user_id', $this->joe()->id)->exists()
        );
    }

    public function test_magic_link_debug_payload_omitted_outside_local(): void
    {
        $this->seedApp();
        $this->assertSame('testing', app()->environment());

        $response = $this->postJson('/api/auth/magic', [
            'email' => 'joe@depotborrow.test',
        ])->assertOk();

        $this->assertArrayNotHasKey('debug_link', $response->json());
    }

    public function test_login_is_rate_limited(): void
    {
        $this->seedApp();

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->postJson('/api/auth/login', [
                'email' => 'nosuch-throttle@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $last->assertStatus(429);
    }

    public function test_unauthenticated_api_returns_401_without_secrets(): void
    {
        $this->seedApp();

        foreach (['/api/settings/smtp', '/api/backups', '/api/admin/users', '/api/auth/me'] as $path) {
            $response = $this->getJson($path)->assertUnauthorized();
            $body = strtolower($response->getContent());
            $this->assertStringNotContainsString('app_key', $body);
            $this->assertStringNotContainsString('db_password', $body);
            $this->assertStringNotContainsString('base64:', $body);
            $this->assertStringNotContainsString('route [login]', $body);
        }
    }

    public function test_public_config_does_not_expose_env_secrets(): void
    {
        $this->seedApp();

        $body = strtolower($this->getJson('/api/auth/config')->assertOk()->getContent());
        foreach (['app_key', 'db_password', 'mail_password', 'twilio', 'auth_token', 'github_token'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    public function test_extension_decision_is_audited(): void
    {
        $this->seedApp();
        $loan = $this->reserveLoanFor($this->joe(), $this->makeItem());

        Sanctum::actingAs($this->mike());
        $this->postJson("/api/loans/{$loan->id}/checkout", [
            'items' => [['item_id' => $loan->items()->value('item_id')]],
        ])->assertOk();

        Sanctum::actingAs($this->joe());
        $extensionId = $this->postJson("/api/loans/{$loan->id}/request-extension", [
            'requested_due_at' => now()->addWeek()->toDateTimeString(),
            'reason' => 'Still on site',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('audit_events', ['event' => 'extension_requested']);

        Sanctum::actingAs($this->mike());
        $this->postJson("/api/loan-extensions/{$extensionId}/decide", [
            'approve' => true,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', ['event' => 'extension_approved']);
    }
}
