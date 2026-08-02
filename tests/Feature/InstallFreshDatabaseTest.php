<?php

namespace Tests\Feature;

use App\Models\InstallationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstallFreshDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_config_reports_not_installed_when_installation_table_missing(): void
    {
        Schema::drop('installation_state');

        $this->getJson('/api/auth/config')
            ->assertOk()
            ->assertJsonPath('installed', false);
    }

    public function test_install_status_works_when_installation_table_missing(): void
    {
        Schema::drop('installation_state');

        $this->getJson('/api/install/status')
            ->assertOk()
            ->assertJsonPath('data.is_installed', false);
    }

    public function test_install_run_creates_admin_and_marks_installed(): void
    {
        $this->postJson('/api/install/run', [
            'admin_name' => 'Site Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password1!secure',
            'app_name' => 'Maintenance Depot Demo',
            'seed_demo_data' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.admin_email', 'admin@example.com');

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertTrue((bool) InstallationState::query()->value('is_installed'));
        $this->assertTrue(User::query()->where('email', 'admin@example.com')->first()->hasRole('it_admin'));
    }
}
