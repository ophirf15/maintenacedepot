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
use Illuminate\Support\Str;

class InstallController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function status()
    {
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
        $state = $this->currentState();

        if ($state->is_installed) {
            return response()->json(['message' => 'Application is already installed.'], 422);
        }

        $data = $request->validate([
            'admin_name' => 'required|string|max:190',
            'admin_email' => 'required|email|max:190',
            'admin_password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'app_name' => 'nullable|string|max:120',
            'seed_demo_data' => 'boolean',
        ]);

        $admin = DB::transaction(function () use ($data, $state) {
            Artisan::call('migrate', ['--force' => true]);

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
}
