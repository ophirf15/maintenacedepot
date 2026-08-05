<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UpdaterService;
use App\Support\SharedHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class UpdaterFlattenedDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlay_writes_build_to_app_root_when_flattened(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@depotborrow.test')->firstOrFail();
        $this->actingAs($admin);

        $flatIndex = base_path('index.php');
        $createdFlatIndex = ! is_file($flatIndex);
        $manifestBackup = null;
        $manifestPath = public_path('build/manifest.json');
        if (is_file($manifestPath)) {
            $manifestBackup = file_get_contents($manifestPath);
        }

        file_put_contents($flatIndex, "<?php // flat\n");
        $this->assertTrue(SharedHosting::isFlattened());

        config(['depot.version' => '1.0.0']);
        app(\App\Services\SettingsService::class)->set('updates', 'github_repo', 'acme/depot', 'string');

        $zipPath = storage_path('app/test-flat-update.zip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('maintenance-depot/VERSION', "1.0.5\n");
        $zip->addFromString('maintenance-depot/public/build/manifest.json', '{"app.js":{}}');
        $zip->addFromString('maintenance-depot/public/build/assets/app.js', 'console.log("v105")');
        $zip->addFromString('maintenance-depot/public/index.php', "<?php // package public index with wrong paths\n");
        $zip->addFromString('maintenance-depot/app/Support/UpdateMarker.php', "<?php\nnamespace App\\Support;\nclass UpdateMarker {}\n");
        $zip->close();

        Http::fake([
            'https://api.github.com/repos/acme/depot/releases/latest' => Http::response([
                'tag_name' => 'v1.0.5',
                'body' => 'Flat deploy sync',
                'assets' => [[
                    'name' => 'maintenance-depot-1.0.5-update.zip',
                    'browser_download_url' => 'https://objects.githubusercontent.com/acme/depot/update.zip',
                ]],
            ]),
            'https://objects.githubusercontent.com/*' => Http::response(file_get_contents($zipPath), 200),
        ]);

        try {
            $result = app(UpdaterService::class)->applyUpdate();

            $this->assertTrue($result['ok'] ?? false, $result['message'] ?? 'update failed');
            $this->assertTrue($result['deploy']['flattened'] ?? false);
            $this->assertFileExists(base_path('build/manifest.json'));
            $this->assertFileExists(base_path('build/assets/app.js'));
            $this->assertFileExists(base_path('public/build/manifest.json'));
            $this->assertStringContainsString("__DIR__.'/vendor/autoload.php'", (string) file_get_contents(base_path('index.php')));
        } finally {
            if ($createdFlatIndex) {
                @unlink($flatIndex);
            }
            if ($manifestBackup !== null) {
                file_put_contents($manifestPath, $manifestBackup);
            }
            @unlink(base_path('app/Support/UpdateMarker.php'));
            @unlink($zipPath);
            @unlink(base_path('build/manifest.json'));
            @unlink(base_path('build/assets/app.js'));
            @rmdir(base_path('build/assets'));
            @rmdir(base_path('build'));
        }
    }
}
