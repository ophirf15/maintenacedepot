<?php

namespace App\Services;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class UpdaterService
{
    public function __construct(private SettingsService $settings) {}

    public function currentVersion(): string
    {
        return config('depot.version', '1.0.0');
    }

    public function checkForUpdates(): array
    {
        $repo = $this->settings->get('updates', 'github_repo', config('depot.github_repo'));
        $token = $this->settings->get('updates', 'github_token');

        if (! $repo) {
            return [
                'current' => $this->currentVersion(),
                'latest' => null,
                'update_available' => false,
                'message' => 'GitHub repo not configured',
            ];
        }

        try {
            $request = Http::acceptJson()->timeout(15);
            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->get("https://api.github.com/repos/{$repo}/releases/latest");
            if (! $response->successful()) {
                return [
                    'current' => $this->currentVersion(),
                    'latest' => null,
                    'update_available' => false,
                    'message' => 'Unable to reach GitHub releases',
                ];
            }

            $latest = ltrim((string) $response->json('tag_name'), 'v');
            $notes = $response->json('body');
            $asset = collect($response->json('assets') ?? [])->first(
                fn ($a) => str_ends_with($a['name'] ?? '', '.zip')
            );

            return [
                'current' => $this->currentVersion(),
                'latest' => $latest,
                'update_available' => version_compare($latest, $this->currentVersion(), '>'),
                'release_notes' => $notes,
                'download_url' => $asset['browser_download_url'] ?? $response->json('zipball_url'),
            ];
        } catch (\Throwable $e) {
            Log::warning('Update check failed', ['error' => $e->getMessage()]);

            return [
                'current' => $this->currentVersion(),
                'latest' => null,
                'update_available' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function applyUpdate(?string $downloadUrl = null): array
    {
        $info = $this->checkForUpdates();
        $url = $downloadUrl ?: ($info['download_url'] ?? null);
        if (! $url || ! ($info['update_available'] ?? false)) {
            return ['ok' => false, 'message' => 'No update available'];
        }

        Artisan::call('down', ['--retry' => 60]);

        try {
            $zipPath = storage_path('app/update-package.zip');
            $bytes = Http::timeout(120)->get($url)->body();
            file_put_contents($zipPath, $bytes);

            $extractTo = storage_path('app/update-extract');
            if (is_dir($extractTo)) {
                $this->rrmdir($extractTo);
            }
            mkdir($extractTo, 0755, true);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Unable to open update zip');
            }
            $zip->extractTo($extractTo);
            $zip->close();

            // In production the release zip contains pre-built app; here we run migrations.
            Artisan::call('migrate', ['--force' => true]);

            $previous = $this->currentVersion();
            $latest = $info['latest'];

            AppVersion::query()->where('is_current', true)->update(['is_current' => false]);
            AppVersion::query()->create([
                'version' => $latest,
                'previous_version' => $previous,
                'applied_at' => now(),
                'applied_by' => auth()->id(),
                'status' => 'applied',
                'release_notes' => $info['release_notes'] ?? null,
                'is_current' => true,
            ]);

            Artisan::call('up');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            return ['ok' => true, 'version' => $latest];
        } catch (\Throwable $e) {
            Artisan::call('up');
            Log::error('Update failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
