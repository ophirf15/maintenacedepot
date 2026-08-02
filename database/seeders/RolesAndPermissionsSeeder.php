<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All permissions available in the Depot Borrow Platform.
     */
    protected array $permissions = [
        'manage_it',
        'manage_users',
        'manage_roles',
        'manage_properties',
        'manage_catalog',
        'manage_inventory',
        'approve_requests',
        'checkout_items',
        'manage_tickets',
        'manage_maintenance',
        'view_audit',
        'manage_settings',
        'manage_updates',
        'view_capex',
        'borrow_items',
        'view_catalog',
        'request_on_behalf',
    ];

    /**
     * Role => permission subset. it_admin always receives every permission.
     */
    protected array $roles = [
        'it_admin' => '*',
        'depot_admin' => [
            'manage_catalog', 'manage_inventory', 'approve_requests', 'checkout_items',
            'manage_tickets', 'manage_maintenance', 'view_audit', 'view_capex',
            'view_catalog', 'borrow_items', 'request_on_behalf',
        ],
        'depot_maintenance' => [
            'manage_tickets', 'manage_maintenance', 'manage_inventory',
            'checkout_items', 'view_catalog',
        ],
        'property_manager' => [
            'approve_requests', 'request_on_behalf', 'view_catalog',
            'borrow_items', 'view_audit',
        ],
        'borrower' => [
            'borrow_items', 'view_catalog',
        ],
    ];

    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($this->roles as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions === '*' ? $this->permissions : $permissions);
        }

        Cache::forget('spatie.permission.cache');
    }
}
