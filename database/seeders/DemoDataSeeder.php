<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\InstallationState;
use App\Models\Item;
use App\Models\ItemLink;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\NotificationType;
use App\Models\Property;
use App\Models\ToolType;
use App\Models\ToolTypeLink;
use App\Models\User;
use App\Services\BorrowService;
use App\Services\ReferenceGenerator;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    use Concerns\SeedsToolTypeSpecs;

    public function run(): void
    {
        $statuses = $this->seedCustomStatuses();
        $this->seedNotificationTypes();
        $properties = $this->seedProperties();
        $depots = $this->seedDepots($properties);
        $toolTypes = $this->seedCatalog();
        $items = $this->seedItems($toolTypes, $depots, $statuses);
        $this->seedAllToolTypeSpecs($toolTypes, $items);
        $users = $this->seedUsers($properties);
        $this->seedSampleRequests($users, $properties, $depots, $items);
        $this->seedMaintenance($items);
        $this->markInstalled($users['admin'] ?? null);
    }

    protected function seedMaintenance(array $items): void
    {
        $oil = MaintenanceType::query()->updateOrCreate(
            ['slug' => 'oil-change'],
            ['name' => 'Oil Change', 'kind' => 'preventive', 'requires_downtime' => true, 'is_active' => true]
        );
        $sharpen = MaintenanceType::query()->updateOrCreate(
            ['slug' => 'blade-sharpen'],
            ['name' => 'Blade Sharpening', 'kind' => 'preventive', 'requires_downtime' => false, 'is_active' => true]
        );
        $gasket = MaintenanceType::query()->updateOrCreate(
            ['slug' => 'gasket-inspect'],
            ['name' => 'Gasket Inspection', 'kind' => 'inspection', 'requires_downtime' => false, 'is_active' => true]
        );

        foreach ($items as $item) {
            $name = $item->name ?? '';
            if (str_contains($name, 'Pressure Washer') || str_contains($name, 'Mower')) {
                MaintenancePlan::query()->updateOrCreate(
                    ['item_id' => $item->id, 'maintenance_type_id' => $oil->id],
                    [
                        'name' => 'Oil change',
                        'trigger_type' => 'usage_hours',
                        'interval_hours' => 50,
                        'next_due_hours' => (float) $item->usage_hours + 50,
                        'blocks_checkout_when_overdue' => true,
                        'is_active' => true,
                    ]
                );
            }
            if (str_contains($name, 'Mower')) {
                MaintenancePlan::query()->updateOrCreate(
                    ['item_id' => $item->id, 'maintenance_type_id' => $sharpen->id],
                    [
                        'name' => 'Sharpen blades',
                        'trigger_type' => 'loan_count',
                        'interval_loans' => 10,
                        'next_due_loans' => (int) $item->lifetime_loan_count + 10,
                        'blocks_checkout_when_overdue' => false,
                        'is_active' => true,
                    ]
                );
            }
            if (str_contains($name, 'Pressure Washer')) {
                MaintenancePlan::query()->updateOrCreate(
                    ['item_id' => $item->id, 'maintenance_type_id' => $gasket->id],
                    [
                        'name' => 'Gasket check',
                        'trigger_type' => 'calendar',
                        'interval_days' => 180,
                        'next_due_at' => now()->addMonths(4),
                        'blocks_checkout_when_overdue' => false,
                        'is_active' => true,
                    ]
                );
                MaintenancePlan::query()->updateOrCreate(
                    ['item_id' => $item->id, 'maintenance_type_id' => $oil->id, 'name' => 'Fuel system flush'],
                    [
                        'name' => 'Fuel system flush',
                        'trigger_type' => 'fuel_cycles',
                        'interval_fuel_cycles' => 8,
                        'next_due_fuel_cycles' => (int) $item->lifetime_fuel_cycles + 8,
                        'blocks_checkout_when_overdue' => false,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    protected function markInstalled(?User $admin): void
    {
        $state = InstallationState::query()->first();
        $payload = [
            'is_installed' => true,
            'current_step' => 'complete',
            'completed_steps' => ['welcome', 'database', 'admin', 'demo', 'complete'],
            'installed_version' => config('depot.version', '1.0.0'),
            'installed_at' => now(),
            'installed_by' => $admin?->id,
        ];

        if ($state) {
            $state->update($payload);
        } else {
            InstallationState::query()->create($payload + [
                'instance_uuid' => (string) Str::uuid(),
            ]);
        }

        app(SettingsService::class)->set('branding', 'app_name', 'Maintenance Depot', 'string', public: true);
        app(SettingsService::class)->set('branding', 'primary_color', '#E7660B', 'string', public: true);
        app(SettingsService::class)->set('branding', 'logo_path', 'branding/logo.png', 'string', public: true);
        app(SettingsService::class)->set('branding', 'favicon_path', 'branding/favicon.png', 'string', public: true);
        app(SettingsService::class)->set(
            'branding',
            'label_ownership',
            'Property of Maintenance Depot — return to Main Depot',
            'string',
            public: true,
        );
        app(SettingsService::class)->set(
            'labels',
            'layout',
            \App\Services\QrLabelService::defaultLayouts(),
            'json',
        );
        app(SettingsService::class)->set('features', 'show_item_holder_property', true, 'bool', public: true);
        app(SettingsService::class)->set('defaults', 'default_max_loan_days', 7, 'int', public: true);
    }

    protected function seedCustomStatuses(): array
    {
        $definitions = [
            ['name' => 'Available', 'slug' => 'available', 'availability_effect' => 'available', 'color' => '#16a34a', 'icon' => 'check-circle', 'is_default' => true, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Reserved', 'slug' => 'reserved', 'availability_effect' => 'in_use', 'color' => '#eab308', 'icon' => 'clock', 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Checked Out', 'slug' => 'checked-out', 'availability_effect' => 'in_use', 'color' => '#2563eb', 'icon' => 'arrow-right-circle', 'is_system' => true, 'sort_order' => 3],
            ['name' => 'Out of Service', 'slug' => 'out-of-service', 'availability_effect' => 'unavailable', 'color' => '#dc2626', 'icon' => 'alert-triangle', 'is_system' => true, 'sort_order' => 4],
            ['name' => 'In Maintenance', 'slug' => 'in-maintenance', 'availability_effect' => 'unavailable', 'color' => '#9333ea', 'icon' => 'wrench', 'is_system' => true, 'sort_order' => 5],
        ];

        $statuses = [];
        foreach ($definitions as $definition) {
            $statuses[$definition['slug']] = CustomStatus::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition + ['is_active' => true]
            );
        }

        return $statuses;
    }

    protected function seedNotificationTypes(): void
    {
        $types = [
            ['key' => 'request.approved', 'name' => 'Request Approved', 'group' => 'requests'],
            ['key' => 'request.modified', 'name' => 'Request Modified', 'group' => 'requests'],
            ['key' => 'request.rejected', 'name' => 'Request Rejected', 'group' => 'requests'],
            ['key' => 'loan.extension_requested', 'name' => 'Extension Requested', 'group' => 'loans'],
            ['key' => 'loan.overdue', 'name' => 'Loan Overdue', 'group' => 'loans'],
        ];

        foreach ($types as $type) {
            NotificationType::query()->updateOrCreate(
                ['key' => $type['key']],
                $type + [
                    'default_channels' => ['in_app'],
                    'available_channels' => ['in_app', 'mail', 'sms'],
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedProperties(): array
    {
        $names = [
            'Pinewood', 'Cedar Ridge', 'Maple Grove', 'Birchwood',
            'Oakview', 'Willow Creek', 'Elm Terrace',
        ];

        $properties = [];
        foreach ($names as $index => $name) {
            $properties[$name] = Property::query()->updateOrCreate(
                ['code' => strtoupper(Str::slug($name, '')).sprintf('%02d', $index + 1)],
                [
                    'name' => $name.' Apartments',
                    'slug' => Str::slug($name).'-apartments',
                    'city' => 'Springfield',
                    'region' => 'IL',
                    'is_active' => true,
                    'is_demo' => true,
                ]
            );
        }

        return $properties;
    }

    protected function seedDepots(array $properties): array
    {
        $main = Depot::query()->updateOrCreate(
            ['code' => 'CENTRAL'],
            [
                'name' => 'Central Depot',
                'type' => 'main',
                'is_pickup_point' => true,
                'is_return_point' => true,
                'default_max_loan_days' => 7,
                'pickup_window_enabled' => true,
                'pickup_window_hours' => 48,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $satellite = Depot::query()->updateOrCreate(
            ['code' => 'PINEWOOD-SAT'],
            [
                'property_id' => $properties['Pinewood']->id,
                'parent_depot_id' => $main->id,
                'name' => 'Pinewood Satellite Shed',
                'type' => 'satellite',
                'is_pickup_point' => true,
                'is_return_point' => true,
                'default_max_loan_days' => 5,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        return ['main' => $main, 'satellite' => $satellite];
    }

    protected function seedCatalog(): array
    {
        $categories = [
            'Pressure Washers' => ['icon' => 'pressure-washer', 'color' => '#0891b2'],
            'Lawn Equipment' => ['icon' => 'lawn-mower', 'color' => '#16a34a'],
            'Hand Tools' => ['icon' => 'tools', 'color' => '#92400e'],
            'Power Tools' => ['icon' => 'tools', 'color' => '#1d4ed8'],
            'Consumables' => ['icon' => 'boxes', 'color' => '#b45309'],
        ];

        $categoryModels = [];
        foreach ($categories as $name => $meta) {
            $categoryModels[$name] = Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                array_merge($meta, ['name' => $name, 'is_active' => true])
            );
        }

        $toolTypeDefs = [
            ['category' => 'Pressure Washers', 'name' => 'Electric Pressure Washer', 'icon' => 'pressure-washer', 'sku_prefix' => 'PW', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Pressure Washers', 'name' => 'Gas Pressure Washer', 'icon' => 'pressure-washer', 'sku_prefix' => 'PWG', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Lawn Equipment', 'name' => 'Push Mower', 'icon' => 'lawn-mower', 'sku_prefix' => 'MOW', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Lawn Equipment', 'name' => 'Riding Mower', 'icon' => 'riding-mower', 'sku_prefix' => 'RMOW', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Hand Tools', 'name' => 'Round Point Shovel', 'icon' => 'shovel', 'sku_prefix' => 'SHV', 'default_loan_days' => 7, 'max_loan_days' => 14],
            ['category' => 'Hand Tools', 'name' => 'Square Point Shovel', 'icon' => 'shovel', 'sku_prefix' => 'SHVS', 'default_loan_days' => 7, 'max_loan_days' => 14],
            // Companions / power tools
            ['category' => 'Power Tools', 'name' => 'Cordless Drill', 'icon' => 'tools', 'sku_prefix' => 'DRL', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => '18V Battery', 'icon' => 'tools', 'sku_prefix' => 'BAT', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Drill Bit Pack', 'icon' => 'tools', 'sku_prefix' => 'BIT', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Reciprocating Saw', 'icon' => 'tools', 'sku_prefix' => 'SAW', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Circular Saw', 'icon' => 'tools', 'sku_prefix' => 'CSW', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'MIG Welder', 'icon' => 'tools', 'sku_prefix' => 'WLD', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Welding Mask', 'icon' => 'tools', 'sku_prefix' => 'MSK', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Air Drain Unclogger', 'icon' => 'tools', 'sku_prefix' => 'UNC', 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Power Tools', 'name' => 'Jerry Can', 'icon' => 'tools', 'sku_prefix' => 'JRY', 'default_loan_days' => 3, 'max_loan_days' => 7],
            // Consumable SKU types
            ['category' => 'Consumables', 'name' => 'Welding Rods', 'icon' => 'boxes', 'sku_prefix' => 'ROD', 'is_consumable' => true, 'default_loan_days' => 1, 'max_loan_days' => 1],
            ['category' => 'Consumables', 'name' => 'Sawzall Blades', 'icon' => 'boxes', 'sku_prefix' => 'SZB', 'is_consumable' => true, 'default_loan_days' => 1, 'max_loan_days' => 1],
            ['category' => 'Consumables', 'name' => 'Circular Saw Blades', 'icon' => 'boxes', 'sku_prefix' => 'CSB', 'is_consumable' => true, 'default_loan_days' => 1, 'max_loan_days' => 1],
            ['category' => 'Consumables', 'name' => 'CO2 Cartridges', 'icon' => 'boxes', 'sku_prefix' => 'CO2', 'is_consumable' => true, 'default_loan_days' => 1, 'max_loan_days' => 1],
        ];

        $toolTypes = [];
        foreach ($toolTypeDefs as $def) {
            $category = $categoryModels[$def['category']];
            $toolTypes[$def['name']] = ToolType::query()->updateOrCreate(
                ['slug' => Str::slug($def['name'])],
                array_merge(
                    array_diff_key($def, ['category' => null]),
                    ['category_id' => $category->id, 'is_active' => true, 'allow_waitlist' => true]
                )
            );
        }

        return $toolTypes;
    }

    protected function seedItems(array $toolTypes, array $depots, array $statuses): array
    {
        $refs = app(ReferenceGenerator::class);
        $available = $statuses['available'];
        $items = [];

        $counts = [
            'Electric Pressure Washer' => 3,
            'Gas Pressure Washer' => 2,
            'Push Mower' => 4,
            'Riding Mower' => 1,
            'Round Point Shovel' => 6,
            'Square Point Shovel' => 4,
            'Cordless Drill' => 2,
            '18V Battery' => 6,
            'Drill Bit Pack' => 3,
            'Reciprocating Saw' => 2,
            'Circular Saw' => 2,
            'MIG Welder' => 1,
            'Welding Mask' => 2,
            'Air Drain Unclogger' => 1,
            'Jerry Can' => 3,
        ];

        foreach ($counts as $toolTypeName => $count) {
            $toolType = $toolTypes[$toolTypeName];
            $prefix = strtoupper($toolType->sku_prefix ?: 'AST');

            for ($i = 1; $i <= $count; $i++) {
                $depot = $i % 4 === 0 ? $depots['satellite'] : $depots['main'];
                // Deterministic tag so updateOrCreate is idempotent on re-seeding.
                $assetTag = $prefix.'-DEMO-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                $existing = Item::query()->where('asset_tag', $assetTag)->first();

                $items[] = Item::query()->updateOrCreate(
                    ['asset_tag' => $assetTag],
                    [
                        'depot_id' => $depot->id,
                        'home_depot_id' => $depot->id,
                        'tool_type_id' => $toolType->id,
                        'custom_status_id' => $available->id,
                        'name' => $toolType->name.' #'.$i,
                        'condition' => 'good',
                        'is_loanable' => true,
                        'is_consumable' => false,
                        // Preserve qr_token on update to avoid re-generating a unique value.
                        'qr_token' => $existing?->qr_token ?? $refs->qrToken(),
                        'numeric_code' => $existing?->numeric_code ?? $refs->numericCode(),
                        'purchase_date' => now()->subYears(2)->subDays($i * 17),
                        'purchase_price' => 150 + ($i * 50),
                        'lifespan_years' => 5,
                        'replacement_cost' => 180 + ($i * 60),
                    ]
                );
            }
        }

        $items = array_merge($items, $this->seedConsumableSkus($toolTypes, $depots, $available, $refs));
        $this->seedCompanionLinks($toolTypes, $items);

        return $items;
    }

    /**
     * Quantity-tracked SKUs (no individual asset checkout).
     *
     * @param  array<string, ToolType>  $toolTypes
     * @param  array{main: Depot, satellite: Depot}  $depots
     * @return list<Item>
     */
    protected function seedConsumableSkus(array $toolTypes, array $depots, CustomStatus $available, ReferenceGenerator $refs): array
    {
        $defs = [
            [
                'type' => 'Welding Rods',
                'tag' => 'ROD-DEMO-SKU',
                'name' => 'ER70S-6 Welding Rods',
                'stock_qty' => 80,
                'reorder_point' => 20,
                'reorder_qty' => 40,
                'stock_unit' => 'lbs',
                'supplier_name' => 'Lincoln Supply Co',
                'supplier_part_number' => 'ER70S-6-10LB',
                'typical_cost' => 4.75,
            ],
            [
                'type' => 'Sawzall Blades',
                'tag' => 'SZB-DEMO-SKU',
                'name' => 'Bi-Metal Sawzall Blades (6")',
                'stock_qty' => 48,
                'reorder_point' => 12,
                'reorder_qty' => 24,
                'stock_unit' => 'ea',
                'supplier_name' => 'Milwaukee Direct',
                'supplier_part_number' => '48-00-5184',
                'typical_cost' => 3.25,
            ],
            [
                'type' => 'Circular Saw Blades',
                'tag' => 'CSB-DEMO-SKU',
                'name' => '7-1/4" Framing Blades',
                'stock_qty' => 16,
                'reorder_point' => 4,
                'reorder_qty' => 8,
                'stock_unit' => 'ea',
                'supplier_name' => 'Dewalt Parts',
                'supplier_part_number' => 'DW3578',
                'typical_cost' => 12.50,
            ],
            [
                'type' => 'CO2 Cartridges',
                'tag' => 'CO2-DEMO-SKU',
                'name' => '16g CO2 Cartridges',
                'stock_qty' => 60,
                'reorder_point' => 15,
                'reorder_qty' => 30,
                'stock_unit' => 'ea',
                'supplier_name' => 'Plumbing Depot Supply',
                'supplier_part_number' => 'CO2-16G-BOX',
                'typical_cost' => 1.10,
            ],
        ];

        $created = [];
        foreach ($defs as $def) {
            $existing = Item::query()->where('asset_tag', $def['tag'])->first();
            $created[] = Item::query()->updateOrCreate(
                ['asset_tag' => $def['tag']],
                [
                    'depot_id' => $depots['main']->id,
                    'home_depot_id' => $depots['main']->id,
                    'tool_type_id' => $toolTypes[$def['type']]->id,
                    'custom_status_id' => $available->id,
                    'name' => $def['name'],
                    'condition' => 'new',
                    'is_loanable' => false,
                    'is_consumable' => true,
                    'stock_qty' => $def['stock_qty'],
                    'reorder_point' => $def['reorder_point'],
                    'reorder_qty' => $def['reorder_qty'],
                    'stock_unit' => $def['stock_unit'],
                    'supplier_name' => $def['supplier_name'],
                    'supplier_part_number' => $def['supplier_part_number'],
                    'typical_cost' => $def['typical_cost'],
                    'qr_token' => $existing?->qr_token ?? $refs->qrToken(),
                    'numeric_code' => $existing?->numeric_code ?? $refs->numericCode(),
                ]
            );
        }

        return $created;
    }

    /**
     * @param  array<string, ToolType>  $toolTypes
     * @param  list<Item>  $items
     */
    protected function seedCompanionLinks(array $toolTypes, array $items): void
    {
        $byType = collect($items)->groupBy(fn (Item $i) => $i->tool_type_id);

        foreach ($items as $item) {
            if ($item->is_consumable && ! $item->relationLoaded('toolType')) {
                $item->load('toolType');
            }
        }

        $skuByTypeName = collect($items)
            ->filter(fn (Item $i) => $i->is_consumable)
            ->keyBy(fn (Item $i) => $i->toolType?->name);

        $typeLinks = [
            ['Cordless Drill', '18V Battery', 'companion', true],
            ['Cordless Drill', 'Drill Bit Pack', 'companion', false],
            ['Reciprocating Saw', '18V Battery', 'companion', true],
            ['Reciprocating Saw', 'Sawzall Blades', 'consumable', false],
            ['Circular Saw', 'Circular Saw Blades', 'consumable', false],
            ['Gas Pressure Washer', 'Jerry Can', 'companion', false],
            ['Push Mower', 'Jerry Can', 'companion', false],
            ['MIG Welder', 'Welding Mask', 'companion', true],
            ['MIG Welder', 'Welding Rods', 'consumable', false],
            ['Air Drain Unclogger', 'CO2 Cartridges', 'consumable', true],
        ];

        foreach ($typeLinks as [$parent, $child, $role, $required]) {
            ToolTypeLink::query()->updateOrCreate(
                [
                    'parent_tool_type_id' => $toolTypes[$parent]->id,
                    'child_tool_type_id' => $toolTypes[$child]->id,
                    'role' => $role,
                ],
                ['is_required' => $required]
            );
        }

        $welder = $byType->get($toolTypes['MIG Welder']->id)?->first();
        $mask = $byType->get($toolTypes['Welding Mask']->id)?->first();
        $rods = $skuByTypeName->get('Welding Rods');
        $drill = $byType->get($toolTypes['Cordless Drill']->id)?->first();
        $bitPack = $byType->get($toolTypes['Drill Bit Pack']->id)?->first();
        $saw = $byType->get($toolTypes['Reciprocating Saw']->id)?->first();
        $szb = $skuByTypeName->get('Sawzall Blades');
        $unclogger = $byType->get($toolTypes['Air Drain Unclogger']->id)?->first();
        $co2 = $skuByTypeName->get('CO2 Cartridges');

        $itemLinks = [
            [$welder, $mask, 'companion', true],
            [$welder, $rods, 'consumable', false],
            [$drill, $bitPack, 'companion', false],
            [$saw, $szb, 'consumable', false],
            [$unclogger, $co2, 'consumable', true],
        ];

        foreach ($itemLinks as [$parent, $child, $role, $required]) {
            if (! $parent || ! $child) {
                continue;
            }
            ItemLink::query()->updateOrCreate(
                ['parent_item_id' => $parent->id, 'child_item_id' => $child->id],
                ['role' => $role, 'is_required' => $required]
            );
            if ($role === 'companion') {
                $parent->update(['is_kit' => true]);
            }
        }
    }

    protected function seedUsers(array $properties): array
    {
        $propertyList = array_values($properties);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@depotborrow.test'],
            [
                'name' => 'Ava Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'default_property_id' => $propertyList[0]->id,
            ]
        );
        $admin->syncRoles(['it_admin']);

        $mike = User::query()->updateOrCreate(
            ['email' => 'mike@depotborrow.test'],
            [
                'name' => 'Mike Martinez',
                'password' => Hash::make('password'),
                'job_title' => 'Depot Manager',
                'is_active' => true,
                'default_property_id' => $propertyList[0]->id,
            ]
        );
        $mike->syncRoles(['depot_admin']);

        $joe = User::query()->updateOrCreate(
            ['email' => 'joe@depotborrow.test'],
            [
                'name' => 'Joe Bishop',
                'password' => Hash::make('password'),
                'job_title' => 'Maintenance Tech',
                'is_active' => true,
                'default_property_id' => $propertyList[0]->id,
            ]
        );
        $joe->syncRoles(['borrower']);

        foreach ([$admin, $mike, $joe] as $user) {
            foreach (array_slice($propertyList, 0, 2) as $property) {
                $property->users()->syncWithoutDetaching([
                    $user->id => ['is_default' => $property->id === $propertyList[0]->id, 'is_active' => true],
                ]);
            }
        }

        return ['admin' => $admin, 'mike' => $mike, 'joe' => $joe];
    }

    protected function seedSampleRequests(array $users, array $properties, array $depots, array $items): void
    {
        $borrowService = app(BorrowService::class);
        $pinewood = $properties['Pinewood'];
        $mower = collect($items)->first(fn (Item $item) => str_contains($item->name ?? '', 'Push Mower'));
        $shovel = collect($items)->first(fn (Item $item) => str_contains($item->name ?? '', 'Round Point Shovel'));

        if (! $mower || ! $shovel) {
            return;
        }

        $draft = $borrowService->createDraft($users['joe'], [
            'property_id' => $pinewood->id,
            'pickup_depot_id' => $depots['main']->id,
            'priority' => 'normal',
            'purpose' => 'Weekly grounds maintenance',
            'needed_from' => now()->addDay(),
            'needed_until' => now()->addDays(3),
            'lines' => [
                ['request_mode' => 'specific_item', 'item_id' => $mower->id, 'quantity' => 1],
            ],
        ]);
        $borrowService->submit($draft);

        $borrowService->createDraft($users['mike'], [
            'property_id' => $pinewood->id,
            'pickup_depot_id' => $depots['satellite']->id,
            'priority' => 'low',
            'purpose' => 'Flower bed edging',
            'needed_from' => now()->addDays(2),
            'needed_until' => now()->addDays(4),
            'lines' => [
                ['request_mode' => 'specific_item', 'item_id' => $shovel->id, 'quantity' => 1],
            ],
        ]);
    }
}
