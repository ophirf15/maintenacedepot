<?php

namespace App\Services;

use App\Models\AppVersion;
use App\Support\SharedHosting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class UpdaterService
{
    /** Paths / patterns that must never be overwritten by an update package. */
    protected array $preserveRelative = [
        '.env',
        '.env.backup',
        '.env.local',
        '.env.production',
        'auth.json',
        'database/database.sqlite',
    ];

    protected array $skipDirectoryPrefixes = [
        'storage/app/public/',
        'storage/app/private/',
        'storage/framework/',
        'storage/logs/',
        'storage/pail/',
        'node_modules/',
        '.git/',
        'tests/',
    ];

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
            $assets = collect($response->json('assets') ?? []);
            $asset = $assets->first(fn ($a) => str_ends_with($a['name'] ?? '', '-update.zip'))
                ?? $assets->first(function ($a) {
                    $name = $a['name'] ?? '';

                    return str_ends_with($name, '.zip')
                        && ! str_ends_with($name, '-install.zip')
                        && ! str_ends_with($name, '-update.zip');
                })
                ?? $assets->first(fn ($a) => str_ends_with($a['name'] ?? '', '.zip'));

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

        $filesApplied = false;
        $deploy = [
            'flattened' => SharedHosting::isFlattened(),
            'manifest' => null,
        ];

        try {
            $zipPath = storage_path('app/update-package.zip');
            $bytes = Http::timeout(180)->get($url)->body();
            if ($bytes === '' || $bytes === false) {
                throw new \RuntimeException('Downloaded update package was empty');
            }
            file_put_contents($zipPath, $bytes);

            $extractTo = storage_path('app/update-extract');
            if (is_dir($extractTo)) {
                $this->rrmdir($extractTo);
            }
            mkdir($extractTo, 0755, true);

            $header = (string) file_get_contents($zipPath, false, null, 0, 4);
            if (! str_starts_with($header, 'PK')) {
                throw new \RuntimeException(
                    'Download was not a zip file (check the release asset URL). Got: '.substr(trim((string) file_get_contents($zipPath, false, null, 0, 80)), 0, 60)
                );
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Unable to open update zip');
            }
            $zip->extractTo($extractTo);
            $zip->close();

            $packageRoot = $this->resolvePackageRoot($extractTo);
            $this->overlayPackage($packageRoot, base_path());
            $deploy = array_merge($deploy, $this->syncWebRootAssets());
            $filesApplied = true;

            Artisan::call('migrate', ['--force' => true]);

            $previous = $this->currentVersion();
            $latest = $info['latest'];
            $this->writeInstalledVersion($latest);
            $this->recordAppliedVersion($latest, $previous, $info['release_notes'] ?? null);

            return [
                'ok' => true,
                'version' => $latest,
                'deploy' => $deploy,
            ];
        } catch (\Throwable $e) {
            Log::error('Update failed', [
                'error' => $e->getMessage(),
                'files_applied' => $filesApplied,
                'deploy' => $deploy,
            ]);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'files_applied' => $filesApplied,
                'deploy' => $deploy,
            ];
        } finally {
            Artisan::call('up');
            if ($filesApplied) {
                try {
                    $this->syncWebRootAssets();
                    Artisan::call('config:clear');
                    Artisan::call('cache:clear');
                    Artisan::call('view:clear');
                    Artisan::call('route:clear');
                } catch (\Throwable $clearError) {
                    Log::warning('Post-update cache clear failed', ['error' => $clearError->getMessage()]);
                }
            }
        }
    }

    protected function resolvePackageRoot(string $extractTo): string
    {
        if (is_file($extractTo.DIRECTORY_SEPARATOR.'artisan')
            || is_file($extractTo.DIRECTORY_SEPARATOR.'VERSION')
            || is_dir($extractTo.DIRECTORY_SEPARATOR.'app')
        ) {
            return $extractTo;
        }

        foreach (scandir($extractTo) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $candidate = $extractTo.DIRECTORY_SEPARATOR.$item;
            if (is_dir($candidate) && (
                is_file($candidate.DIRECTORY_SEPARATOR.'artisan')
                || is_file($candidate.DIRECTORY_SEPARATOR.'VERSION')
                || is_dir($candidate.DIRECTORY_SEPARATOR.'app')
            )) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Update package root not found in zip');
    }

    protected function overlayPackage(string $from, string $to): void
    {
        $flat = SharedHosting::isFlattened($to);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($from) + 1));
            if ($relative === '' || $this->shouldSkipRelative($relative)) {
                continue;
            }

            $targets = $this->resolveOverlayTargets($to, $relative, $flat);

            foreach ($targets as $target) {
                if ($item->isDir()) {
                    if (! is_dir($target)) {
                        mkdir($target, 0755, true);
                    }

                    continue;
                }

                $dir = dirname($target);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (! @copy($item->getPathname(), $target)) {
                    throw new \RuntimeException("Unable to update file: {$relative} → {$target}");
                }
            }
        }

        if ($flat) {
            $this->writeFlattenedIndexPhp($to);
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveOverlayTargets(string $to, string $relative, bool $flat): array
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $primary = $to.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $targets = [$primary];

        if (! $flat || ! str_starts_with($relative, 'public/')) {
            return $targets;
        }

        $webRelative = substr($relative, strlen('public/'));

        // Package public/index.php uses ../vendor paths — never overwrite the flat index with it.
        if ($webRelative === 'index.php' || $webRelative === '') {
            return $targets;
        }

        $targets[] = $to.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $webRelative);

        return array_values(array_unique($targets));
    }

    protected function writeFlattenedIndexPhp(string $to): void
    {
        $path = $to.DIRECTORY_SEPARATOR.'index.php';
        if (! @file_put_contents($path, SharedHosting::flattenedIndexPhp())) {
            throw new \RuntimeException('Unable to write flattened index.php');
        }
    }

    /**
     * Ensure web-visible build/brand assets exist where the browser and @vite expect them.
     *
     * @return array{flattened: bool, manifest: string|null, mirrored: list<string>}
     */
    protected function syncWebRootAssets(): array
    {
        $flat = SharedHosting::isFlattened();
        $mirrored = [];

        $packageBuild = base_path('public/build');
        $webBuild = $flat ? base_path('build') : public_path('build');

        if (is_dir($packageBuild)) {
            File::ensureDirectoryExists($webBuild);
            File::copyDirectory($packageBuild, $webBuild);
            $mirrored[] = $webBuild;

            // Keep both trees identical on flat hosts (Laravel may still resolve either).
            if ($flat && realpath($packageBuild) !== realpath($webBuild)) {
                File::ensureDirectoryExists($packageBuild);
                File::copyDirectory($webBuild, $packageBuild);
                $mirrored[] = $packageBuild;
            }
        }

        if ($flat) {
            foreach (['.htaccess', 'favicon.ico', 'favicon.svg', 'manifest.webmanifest', 'sw.js', 'VERSION'] as $file) {
                $src = base_path('public/'.$file);
                if (is_file($src)) {
                    @copy($src, base_path($file));
                    $mirrored[] = base_path($file);
                }
            }

            if (is_dir(base_path('public/brand'))) {
                File::ensureDirectoryExists(base_path('brand'));
                File::copyDirectory(base_path('public/brand'), base_path('brand'));
                $mirrored[] = base_path('brand');
            }

            if (is_dir(base_path('public/icons'))) {
                File::ensureDirectoryExists(base_path('icons'));
                File::copyDirectory(base_path('public/icons'), base_path('icons'));
                $mirrored[] = base_path('icons');
            }

            $this->writeFlattenedIndexPhp(base_path());
        }

        $manifest = $flat ? base_path('build/manifest.json') : public_path('build/manifest.json');
        if (! is_file($manifest)) {
            // Last resort: find any manifest written by the package.
            foreach ([base_path('build/manifest.json'), base_path('public/build/manifest.json')] as $candidate) {
                if (is_file($candidate)) {
                    $manifest = $candidate;
                    break;
                }
            }
        }

        if (! is_file($manifest)) {
            throw new \RuntimeException(
                'Update package is missing build/manifest.json after sync. Flattened='.($flat ? 'yes' : 'no')
            );
        }

        // On flat hosts the browser loads /build/* from the app root.
        if ($flat && ! is_file(base_path('build/manifest.json'))) {
            File::ensureDirectoryExists(base_path('build'));
            File::copyDirectory(dirname($manifest), base_path('build'));
        }

        return [
            'flattened' => $flat,
            'manifest' => is_file(base_path('build/manifest.json'))
                ? base_path('build/manifest.json')
                : $manifest,
            'mirrored' => array_values(array_unique($mirrored)),
        ];
    }

    protected function shouldSkipRelative(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if (in_array($relative, $this->preserveRelative, true)) {
            return true;
        }

        foreach ($this->skipDirectoryPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        if (str_ends_with($relative, '.sqlite') || str_ends_with($relative, '.sqlite-journal')) {
            return true;
        }

        return false;
    }

    protected function recordAppliedVersion(string $latest, string $previous, ?string $releaseNotes): void
    {
        AppVersion::query()->where('is_current', true)->update(['is_current' => false]);

        AppVersion::query()->updateOrCreate(
            ['version' => $latest],
            [
                'previous_version' => $previous,
                'applied_at' => now(),
                'applied_by' => auth()->id(),
                'status' => 'applied',
                'release_notes' => $releaseNotes,
                'is_current' => true,
            ]
        );
    }

    protected function writeInstalledVersion(string $version): void
    {
        file_put_contents(base_path('VERSION'), $version.PHP_EOL);
        @file_put_contents(public_path('VERSION'), $version.PHP_EOL);

        $envPath = base_path('.env');
        if (is_file($envPath) && ! app()->environment('testing')) {
            $env = (string) file_get_contents($envPath);
            if (preg_match('/^DEPOT_VERSION=.*$/m', $env)) {
                $env = preg_replace('/^DEPOT_VERSION=.*$/m', 'DEPOT_VERSION='.$version, $env) ?? $env;
            } else {
                $env = rtrim($env).PHP_EOL.'DEPOT_VERSION='.$version.PHP_EOL;
            }
            file_put_contents($envPath, $env);
        }

        config(['depot.version' => $version]);
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
