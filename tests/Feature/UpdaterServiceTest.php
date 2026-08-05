<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use App\Models\User;
use App\Services\UpdaterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class UpdaterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_update_overlays_package_files_and_writes_version(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@depotborrow.test')->firstOrFail();
        $this->actingAs($admin);

        config(['depot.version' => '1.0.0']);
        app(\App\Services\SettingsService::class)->set('updates', 'github_repo', 'acme/depot', 'string');

        $zipPath = storage_path('app/test-update.zip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('maintenance-depot/VERSION', "1.0.1\n");
        $zip->addFromString('maintenance-depot/app/Support/UpdateMarker.php', "<?php\nnamespace App\\Support;\nclass UpdateMarker {}\n");
        $zip->addFromString('maintenance-depot/.env', "APP_KEY=should-not-copy\n");
        $zip->close();

        Http::fake([
            'https://api.github.com/repos/acme/depot/releases/latest' => Http::response([
                'tag_name' => 'v1.0.1',
                'body' => 'Storage + updater fixes',
                'assets' => [[
                    'name' => 'maintenance-depot-1.0.1-update.zip',
                    'browser_download_url' => 'https://objects.githubusercontent.com/acme/depot/update.zip',
                ]],
            ]),
            'https://objects.githubusercontent.com/*' => Http::response(file_get_contents($zipPath), 200),
        ]);

        $result = app(UpdaterService::class)->applyUpdate();

        $this->assertTrue($result['ok'] ?? false, $result['message'] ?? 'update failed');
        $this->assertSame('1.0.1', $result['version']);
        $this->assertSame('1.0.1', trim((string) file_get_contents(base_path('VERSION'))));
        $this->assertFileExists(base_path('app/Support/UpdateMarker.php'));
        $this->assertStringNotContainsString('should-not-copy', (string) @file_get_contents(base_path('.env')));
        $this->assertDatabaseHas('app_versions', ['version' => '1.0.1', 'is_current' => 1]);

        @unlink(base_path('app/Support/UpdateMarker.php'));
        @unlink($zipPath);
    }

    public function test_apply_update_is_idempotent_for_same_version_row(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@depotborrow.test')->firstOrFail();
        $this->actingAs($admin);

        config(['depot.version' => '1.0.0']);
        app(\App\Services\SettingsService::class)->set('updates', 'github_repo', 'acme/depot', 'string');

        AppVersion::query()->create([
            'version' => '1.0.1',
            'previous_version' => '1.0.0',
            'applied_at' => now()->subMinute(),
            'applied_by' => $admin->id,
            'status' => 'applied',
            'is_current' => false,
        ]);

        $zipPath = storage_path('app/test-update-idempotent.zip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('maintenance-depot/VERSION', "1.0.1\n");
        $zip->close();

        Http::fake([
            'https://api.github.com/repos/acme/depot/releases/latest' => Http::response([
                'tag_name' => 'v1.0.1',
                'body' => 'retry',
                'assets' => [[
                    'name' => 'maintenance-depot-1.0.1-update.zip',
                    'browser_download_url' => 'https://objects.githubusercontent.com/acme/depot/update.zip',
                ]],
            ]),
            'https://objects.githubusercontent.com/*' => Http::response(file_get_contents($zipPath), 200),
        ]);

        $result = app(UpdaterService::class)->applyUpdate();

        $this->assertTrue($result['ok'] ?? false, $result['message'] ?? 'update failed');
        $this->assertSame(1, AppVersion::query()->where('version', '1.0.1')->count());
        $this->assertTrue((bool) AppVersion::query()->where('version', '1.0.1')->value('is_current'));

        @unlink($zipPath);
    }

    public function test_apply_update_refuses_download_host_outside_allowlist(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@depotborrow.test')->firstOrFail();
        $this->actingAs($admin);

        config(['depot.version' => '1.0.0']);
        app(\App\Services\SettingsService::class)->set('updates', 'github_repo', 'acme/depot', 'string');

        Http::fake([
            'https://api.github.com/repos/acme/depot/releases/latest' => Http::response([
                'tag_name' => 'v1.0.1',
                'body' => 'allowlist',
                'assets' => [[
                    'name' => 'maintenance-depot-1.0.1-update.zip',
                    'browser_download_url' => 'https://objects.githubusercontent.com/acme/depot/update.zip',
                ]],
            ]),
        ]);

        $result = app(UpdaterService::class)->applyUpdate('https://attacker.example/evil.zip');

        $this->assertFalse($result['ok'] ?? true);
        $this->assertSame('Update package host is not allowed', $result['message']);
        $this->assertFalse($result['files_applied'] ?? true);
        $this->assertSame('1.0.0', $this->app->make(UpdaterService::class)->currentVersion());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'attacker.example'));
    }
}
