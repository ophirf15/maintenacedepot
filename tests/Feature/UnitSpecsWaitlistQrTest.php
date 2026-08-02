<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\ToolTypeSpecField;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\QrLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitSpecsWaitlistQrTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->where('email', 'admin@depotborrow.test')->firstOrFail();
    }

    private function borrower(): User
    {
        return User::query()->where('email', 'joe@depotborrow.test')->firstOrFail();
    }

    private function inventoryAdmin(): User
    {
        return User::query()->where('email', 'mike@depotborrow.test')->firstOrFail();
    }

    public function test_spec_fields_and_values_appear_on_catalog_items(): void
    {
        $this->seed();

        Sanctum::actingAs($this->admin());

        $type = ToolType::query()->where('name', 'Electric Pressure Washer')->firstOrFail();

        $this->postJson("/api/tool-types/{$type->id}/spec-fields", [
            'label' => 'Hose length',
            'unit' => 'ft',
            'field_type' => 'number',
        ])->assertCreated();

        $field = ToolTypeSpecField::query()->where('tool_type_id', $type->id)->where('key', 'hose_length')->firstOrFail();

        $item = Item::query()->where('tool_type_id', $type->id)->firstOrFail();

        Sanctum::actingAs($this->inventoryAdmin());
        $this->putJson("/api/items/{$item->id}", [
            'depot_id' => $item->depot_id,
            'tool_type_id' => $item->tool_type_id,
            'custom_status_id' => $item->custom_status_id,
            'specs' => ['hose_length' => '25', 'psi' => '3000'],
        ])->assertOk();

        Sanctum::actingAs($this->borrower());
        $payload = $this->getJson("/api/catalog/tool-types/{$type->id}/items")->assertOk()->json('data');
        $row = collect($payload)->firstWhere('id', $item->id);

        $this->assertNotEmpty($row['specs']);
        $displays = collect($row['specs'])->pluck('display')->all();
        $this->assertTrue(collect($displays)->contains(fn ($d) => str_contains($d, '3000')));
    }

    public function test_image_upload_sets_image_path(): void
    {
        $this->seed();
        Storage::fake('public');

        $item = Item::query()->firstOrFail();
        Sanctum::actingAs($this->inventoryAdmin());

        $this->postJson("/api/items/{$item->id}/image", [
            'image' => UploadedFile::fake()->image('washer.jpg', 400, 300),
        ])->assertOk()->assertJsonPath('data.image_path', fn ($path) => str_starts_with((string) $path, 'items/'));

        $item->refresh();
        $this->assertNotNull($item->image_path);
        $this->assertNotNull($item->image_url);
    }

    public function test_borrower_can_join_and_leave_waitlist(): void
    {
        $this->seed();

        $type = ToolType::query()->where('allow_waitlist', true)->firstOrFail();
        $propertyId = Property::query()->value('id');

        Sanctum::actingAs($this->borrower());

        $this->postJson('/api/waitlist', [
            'tool_type_id' => $type->id,
            'property_id' => $propertyId,
            'desired_from' => now()->addDay()->toIso8601String(),
            'desired_until' => now()->addDays(4)->toIso8601String(),
        ])->assertCreated();

        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $this->borrower()->id,
            'tool_type_id' => $type->id,
            'status' => 'waiting',
        ]);

        $entry = WaitlistEntry::query()->where('user_id', $this->borrower()->id)->firstOrFail();

        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/waitlist/{$entry->id}")->assertOk();
        $this->assertSame('cancelled', $entry->fresh()->status);
    }

    public function test_qr_label_streams_png_and_sheet_returns_pdf(): void
    {
        $this->seed();
        Storage::fake('public');

        $item = Item::query()->firstOrFail();
        Sanctum::actingAs($this->inventoryAdmin());

        $this->postJson("/api/qr/items/{$item->id}/generate")
            ->assertOk()
            ->assertJsonPath('data.download_url', "/api/qr/items/{$item->id}/label?size=standard")
            ->assertJsonPath('data.url', fn ($url) => str_starts_with((string) $url, '/storage/'));

        $label = $this->get("/api/qr/items/{$item->id}/label")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $png = method_exists($label, 'streamedContent')
            ? $label->streamedContent()
            : $label->getContent();
        $this->assertNotSame('', $png);
        $this->assertNotFalse(@imagecreatefromstring($png), 'Label response should be a valid PNG');
        $size = getimagesizefromstring($png);
        $this->assertGreaterThan(500, $size[0], 'Label should be wide enough for a label printer');
        $this->assertGreaterThan(200, $size[1], 'Label should include text and barcode height');

        $this->postJson('/api/qr/sheet', ['item_ids' => [$item->id]])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_label_size_presets_and_niimbot_dimensions(): void
    {
        $this->seed();
        Storage::fake('public');
        Sanctum::actingAs($this->inventoryAdmin());

        $this->getJson('/api/qr/sizes')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'standard')
            ->assertJsonFragment(['key' => 'niimbot_15x30']);

        $item = Item::query()->firstOrFail();

        $tiny = $this->get("/api/qr/items/{$item->id}/label?size=niimbot_15x30")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $png = method_exists($tiny, 'streamedContent') ? $tiny->streamedContent() : $tiny->getContent();
        $dims = getimagesizefromstring($png);
        $this->assertSame(192, $dims[0]);
        $this->assertSame(96, $dims[1]);

        $this->getJson("/api/qr/items/{$item->id}/label?size=not-a-size")
            ->assertStatus(422);

        $this->postJson('/api/qr/export-zip', ['item_ids' => [$item->id], 'size' => 'medium'])
            ->assertOk();
    }

    public function test_branding_label_ownership_appears_on_labels(): void
    {
        $this->seed();
        Storage::fake('public');

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/settings/branding', [
            'label_ownership' => 'Property of ACME Yard — return to Gate B',
        ])->assertOk()
            ->assertJsonPath('data.label_ownership', 'Property of ACME Yard — return to Gate B');

        $this->assertSame(
            'Property of ACME Yard — return to Gate B',
            app(QrLabelService::class)->ownershipLine()
        );

        Sanctum::actingAs($this->inventoryAdmin());
        $item = Item::query()->firstOrFail();

        $this->get("/api/qr/items/{$item->id}/label?size=niimbot_15x30")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->get("/api/qr/items/{$item->id}/label?size=standard")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_label_layout_builder_persists_and_reflows(): void
    {
        $this->seed();
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $defaults = QrLabelService::defaultLayouts();
        $defaults['niimbot_15x30']['name'] = false;
        $defaults['niimbot_15x30']['ownership'] = false;

        $this->putJson('/api/settings/labels', ['layout' => $defaults])
            ->assertOk()
            ->assertJsonPath('data.layout.niimbot_15x30.name', false)
            ->assertJsonPath('data.layout.niimbot_15x30.numeric_id', true);

        $this->assertFalse(app(QrLabelService::class)->fieldEnabled('niimbot_15x30', 'name'));
        $this->assertTrue(app(QrLabelService::class)->fieldEnabled('niimbot_15x30', 'numeric_id'));

        $this->getJson('/api/settings/labels')
            ->assertOk()
            ->assertJsonPath('data.layout.niimbot_15x30.name', false);

        Sanctum::actingAs($this->inventoryAdmin());
        $item = Item::query()->firstOrFail();

        $tiny = $this->get("/api/qr/items/{$item->id}/label?size=niimbot_15x30")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $png = method_exists($tiny, 'streamedContent') ? $tiny->streamedContent() : $tiny->getContent();
        $dims = getimagesizefromstring($png);
        $this->assertSame(192, $dims[0]);
        $this->assertSame(96, $dims[1]);

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/settings/labels', [
            'layout' => ['not-a-size' => ['qr' => true, 'numeric_id' => true]],
        ])->assertStatus(422);

        $bad = $defaults;
        $bad['niimbot_15x30']['qr'] = false;
        $bad['niimbot_15x30']['numeric_id'] = false;
        $this->putJson('/api/settings/labels', ['layout' => $bad])
            ->assertStatus(422);
    }

    public function test_label_draft_preview_and_placement_options(): void
    {
        $this->seed();
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $item = Item::query()->firstOrFail();
        $draft = QrLabelService::normalizeSizeLayout('niimbot_15x30', [
            'qr' => true,
            'numeric_id' => true,
            'name' => false,
            'ownership' => true,
            'qr_side' => 'right',
            'font' => 'bold',
            'id_size' => 'large',
            'name_size' => 'small',
            'stack_order' => ['ownership', 'numeric_id', 'name'],
            'logo' => false,
        ]);

        $this->assertSame('small', $draft['name_size']);

        $preview = $this->post('/api/qr/preview', [
            'size' => 'niimbot_15x30',
            'item_id' => $item->id,
            'layout' => $draft,
        ]);
        $preview->assertOk()->assertHeader('content-type', 'image/png');
        $png = method_exists($preview, 'streamedContent') ? $preview->streamedContent() : $preview->getContent();
        $dims = getimagesizefromstring($png);
        $this->assertSame(192, $dims[0]);
        $this->assertSame(96, $dims[1]);

        // Draft must not persist until Save.
        $this->assertNotSame('right', app(QrLabelService::class)->layoutFor('niimbot_15x30')['qr_side'] ?? null);

        $all = QrLabelService::defaultLayouts();
        $all['niimbot_15x30'] = $draft;
        $this->putJson('/api/settings/labels', ['layout' => $all])
            ->assertOk()
            ->assertJsonPath('data.layout.niimbot_15x30.qr_side', 'right')
            ->assertJsonPath('data.layout.niimbot_15x30.stack_order.0', 'ownership')
            ->assertJsonPath('data.layout.niimbot_15x30.name_size', 'small');

        $this->assertSame('right', app(QrLabelService::class)->layoutFor('niimbot_15x30')['qr_side']);
        $this->assertSame('ownership', app(QrLabelService::class)->layoutFor('niimbot_15x30')['stack_order'][0]);
        $this->assertSame('small', app(QrLabelService::class)->layoutFor('niimbot_15x30')['name_size']);

        $withName = QrLabelService::normalizeSizeLayout('niimbot_15x30', [
            'qr' => true,
            'numeric_id' => true,
            'name' => true,
            'ownership' => false,
            'name_size' => 'large',
            'id_size' => 'medium',
        ]);
        $previewLarge = $this->post('/api/qr/preview', [
            'size' => 'niimbot_15x30',
            'item_id' => $item->id,
            'layout' => $withName,
        ]);
        $previewLarge->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_branding_logo_upload_sets_logo_path(): void
    {
        $this->seed();
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $file = UploadedFile::fake()->image('company-logo.png', 120, 40);

        $this->post('/api/settings/branding/logo', ['logo' => $file], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_path', 'branding/logo.png')
            ->assertJsonPath('data.url', '/storage/branding/logo.png');

        Storage::disk('public')->assertExists('branding/logo.png');
        $this->assertSame('branding/logo.png', app(\App\Services\SettingsService::class)->get('branding', 'logo_path'));
    }

    public function test_branding_favicon_upload_sets_favicon_path(): void
    {
        $this->seed();
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $file = UploadedFile::fake()->image('favicon.png', 32, 32);

        $this->post('/api/settings/branding/favicon', ['favicon' => $file], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.favicon_path', 'branding/favicon.png')
            ->assertJsonPath('data.url', '/storage/branding/favicon.png');

        Storage::disk('public')->assertExists('branding/favicon.png');
        $this->assertSame('branding/favicon.png', app(\App\Services\SettingsService::class)->get('branding', 'favicon_path'));
    }

    public function test_bulk_label_export_can_include_all_items(): void
    {
        $this->seed();
        Storage::fake('public');

        Sanctum::actingAs($this->inventoryAdmin());

        $zip = $this->postJson('/api/qr/export-zip', ['all' => true]);
        $zip->assertOk();
        $this->assertStringContainsString('zip', strtolower($zip->headers->get('content-type') ?? ''));

        $pdf = $this->postJson('/api/qr/sheet', ['all' => true]);
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_demo_seed_attaches_specs_to_core_tool_types(): void
    {
        $this->seed();

        $types = ToolType::query()->pluck('name');
        foreach (['Electric Pressure Washer', 'Gas Pressure Washer', 'Push Mower', 'Round Point Shovel'] as $name) {
            $this->assertTrue($types->contains($name), "Missing tool type {$name}");
            $type = ToolType::query()->where('name', $name)->firstOrFail();
            $this->assertTrue(
                ToolTypeSpecField::query()->where('tool_type_id', $type->id)->exists(),
                "{$name} should have spec fields"
            );
            $item = Item::query()->where('tool_type_id', $type->id)->with('specValues.field')->firstOrFail();
            $this->assertNotEmpty($item->specs, "{$name} units should have spec values");
        }
    }

    public function test_scan_resolves_qr_token_asset_tag_and_numeric_id(): void
    {
        $this->seed();

        $item = Item::query()->whereNotNull('numeric_code')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $item->numeric_code);
        $this->assertSame($item->numeric_code, $item->numeric_id);
        $this->assertNotNull(QrLabelService::findItemByScanCode($item->qr_token)?->id);
        $this->assertSame($item->id, QrLabelService::findItemByScanCode($item->asset_tag)?->id);
        $this->assertSame($item->id, QrLabelService::findItemByScanCode($item->numeric_code)?->id);

        // Short typos must not resolve (e.g. "11" vs a real 6-digit code).
        $this->assertNull(QrLabelService::findItemByScanCode('11'));
        $this->assertNull(QrLabelService::findItemByScanCode(substr($item->numeric_code, 0, 2)));
        $this->assertNull(QrLabelService::findItemByScanCode('000000'));
    }

    public function test_item_create_can_claim_qr_token(): void
    {
        $this->seed();

        $item = Item::query()->firstOrFail();
        Sanctum::actingAs($this->inventoryAdmin());

        $this->postJson('/api/items', [
            'depot_id' => $item->depot_id,
            'tool_type_id' => $item->tool_type_id,
            'custom_status_id' => $item->custom_status_id,
            'asset_tag' => 'CLAIM-01',
            'qr_token' => 'preprinted-sticker-token-01',
            'condition' => 'good',
            'is_loanable' => true,
        ])->assertCreated()->assertJsonPath('data.qr_token', 'preprinted-sticker-token-01');

        $this->postJson('/api/items', [
            'depot_id' => $item->depot_id,
            'tool_type_id' => $item->tool_type_id,
            'custom_status_id' => $item->custom_status_id,
            'asset_tag' => 'CLAIM-02',
            'qr_token' => 'preprinted-sticker-token-01',
            'condition' => 'good',
        ])->assertStatus(422);
    }
}
