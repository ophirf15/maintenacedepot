<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    protected string $disk = 'local';

    protected string $directory = 'backups';

    public function __construct(private AuditLogger $audit) {}

    public function export(Request $request)
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $filename = 'backup-'.now()->format('Ymd-His').'.sql';
        $path = "{$this->directory}/{$filename}";

        Storage::disk($this->disk)->makeDirectory($this->directory);
        $fullPath = Storage::disk($this->disk)->path($path);

        try {
            match ($config['driver'] ?? null) {
                'sqlite' => copy($config['database'], $fullPath),
                'mysql' => $this->runDump([
                    'mysqldump', '-h', $config['host'], '-P', (string) ($config['port'] ?? 3306),
                    '-u', $config['username'], '--password='.$config['password'], $config['database'],
                ], $fullPath),
                'pgsql' => $this->runDump([
                    'pg_dump', '-h', $config['host'], '-p', (string) ($config['port'] ?? 5432),
                    '-U', $config['username'], '-d', $config['database'], '-f', $fullPath,
                ], $fullPath, env: ['PGPASSWORD' => $config['password'] ?? '']),
                default => throw new \RuntimeException('Unsupported database driver for backup.'),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Backup failed: '.$e->getMessage()], 500);
        }

        $this->audit->log('backup_created', null, null, ['file' => $filename]);

        return response()->json(['data' => $this->fileMeta($path)], 201);
    }

    public function index()
    {
        $files = collect(Storage::disk($this->disk)->files($this->directory))
            ->filter(fn ($path) => str_ends_with($path, '.sql'))
            ->sortByDesc(fn ($path) => Storage::disk($this->disk)->lastModified($path))
            ->values()
            ->map(fn ($path) => $this->fileMeta($path));

        return response()->json(['data' => $files]);
    }

    public function download(string $filename)
    {
        $path = "{$this->directory}/".basename($filename);

        if (! Storage::disk($this->disk)->exists($path)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return Storage::disk($this->disk)->download($path);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $uploaded = $request->file('file');
        $tempPath = $uploaded->getRealPath();

        try {
            match ($config['driver'] ?? null) {
                'sqlite' => copy($tempPath, $config['database']),
                'mysql' => $this->runRestore([
                    'mysql', '-h', $config['host'], '-P', (string) ($config['port'] ?? 3306),
                    '-u', $config['username'], '--password='.$config['password'], $config['database'],
                ], $tempPath),
                'pgsql' => $this->runRestore([
                    'psql', '-h', $config['host'], '-p', (string) ($config['port'] ?? 5432),
                    '-U', $config['username'], '-d', $config['database'], '-f', $tempPath,
                ], $tempPath, env: ['PGPASSWORD' => $config['password'] ?? '']),
                default => throw new \RuntimeException('Unsupported database driver for restore.'),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Import failed: '.$e->getMessage()], 500);
        }

        $this->audit->log('backup_imported', null, null, ['file' => $uploaded->getClientOriginalName()]);

        return response()->json(['ok' => true]);
    }

    protected function runDump(array $command, string $outputPath, array $env = []): void
    {
        $process = new Process($command, null, $env ?: null);
        $process->setTimeout(300);

        if (str_starts_with($command[0], 'mysqldump')) {
            $process->setInput(null);
            $result = $process->mustRun();
            file_put_contents($outputPath, $result->getOutput());

            return;
        }

        $process->mustRun();
    }

    protected function runRestore(array $command, string $inputFile, array $env = []): void
    {
        $process = new Process($command, null, $env ?: null);
        $process->setTimeout(300);
        $process->setInput(fopen($inputFile, 'r'));
        $process->mustRun();
    }

    protected function fileMeta(string $path): array
    {
        return [
            'name' => basename($path),
            'size_bytes' => Storage::disk($this->disk)->size($path),
            'created_at' => date('c', Storage::disk($this->disk)->lastModified($path)),
        ];
    }
}
