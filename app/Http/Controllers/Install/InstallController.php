<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\InstallationState;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function status()
    {
        if (! Schema::hasTable('installation_state')) {
            return response()->json([
                'data' => [
                    'is_installed' => false,
                    'current_step' => 'welcome',
                    'completed_steps' => [],
                    'installed_version' => null,
                    'installed_at' => null,
                    'version' => config('depot.version'),
                ],
            ]);
        }

        $state = $this->currentState();

        return response()->json([
            'data' => [
                'is_installed' => $state->is_installed,
                'current_step' => $state->current_step,
                'completed_steps' => $state->completed_steps ?? [],
                'installed_version' => $state->installed_version,
                'installed_at' => $state->installed_at,
                'version' => config('depot.version'),
            ],
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'admin_name' => 'required|string|max:190',
            'admin_email' => 'required|email|max:190',
            'admin_password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'app_name' => 'nullable|string|max:120',
            'seed_demo_data' => 'boolean',
        ]);

        // Migrations must run before any install-state queries (fresh host DBs have no tables yet).
        // Keep DDL outside the data transaction — MySQL commits DDL implicitly.
        Artisan::call('migrate', ['--force' => true]);
        $this->ensureStorageDirectories();

        $state = $this->currentState();

        if ($state->is_installed) {
            return response()->json(['message' => 'Application is already installed.'], 422);
        }

        $admin = DB::transaction(function () use ($data, $state) {
            (new RolesAndPermissionsSeeder)->run();

            $admin = User::query()->updateOrCreate(
                ['email' => $data['admin_email']],
                [
                    'name' => $data['admin_name'],
                    'password' => Hash::make($data['admin_password']),
                    'is_active' => true,
                ]
            );
            $admin->syncRoles(['it_admin']);

            if ($data['seed_demo_data'] ?? false) {
                (new DemoDataSeeder)->run();
            }

            app(\App\Services\SettingsService::class)->set(
                'branding',
                'app_name',
                $data['app_name'] ?? 'Maintenance Depot',
                'string',
                public: true
            );

            $state->update([
                'is_installed' => true,
                'current_step' => 'complete',
                'completed_steps' => ['welcome', 'database', 'admin', 'complete'],
                'installed_version' => config('depot.version'),
                'installed_at' => now(),
                'installed_by' => $admin->id,
            ]);

            return $admin;
        });

        $this->audit->log('app_installed', $admin, null, ['email' => $admin->email]);

        return response()->json([
            'data' => [
                'ok' => true,
                'admin_email' => $admin->email,
            ],
        ], 201);
    }

    protected function currentState(): InstallationState
    {
        return InstallationState::query()->firstOrCreate([], [
            'instance_uuid' => (string) Str::uuid(),
            'is_installed' => false,
            'current_step' => 'welcome',
        ]);
    }

    protected function ensureStorageDirectories(): void
    {
        foreach ([
            storage_path('app/public/branding'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Best-effort symlink; PublicStorageController covers hosts that disallow it.
        $link = public_path('storage');
        $target = storage_path('app/public');
        if (! file_exists($link) && is_dir($target)) {
            @symlink($target, $link);
        }
    }
}
