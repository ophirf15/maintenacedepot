<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomStatus;
use App\Models\Depot;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\Property;
use App\Models\ReturnInspection;
use App\Models\Ticket;
use App\Models\ToolType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\BorrowService;
use App\Services\ReferenceGenerator;
use Database\Seeders\Concerns\SeedsToolTypeSpecs;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Volume test data on top of DemoDataSeeder: a wider catalog plus maintenance
 * history, tickets, work orders and loans in every state, so the servicing and
 * budget screens have something realistic to show.
 *
 * Run on its own: php artisan db:seed --class=TestDataSeeder
 * Every record uses a deterministic reference so re-running updates in place.
 */
class TestDataSeeder extends Seeder
{
    use SeedsToolTypeSpecs;

    private array $statuses = [];

    private array $users = [];

    public function run(): void
    {
        $this->statuses = CustomStatus::query()->get()->keyBy('slug')->all();

        if (! isset($this->statuses['available'])) {
            $this->command?->error('Run DemoDataSeeder first — core statuses are missing.');

            return;
        }

        $this->users = $this->seedPeople();
        $toolTypes = $this->seedExtraCatalog();
        $items = $this->seedExtraItems($toolTypes);
        $this->seedAllToolTypeSpecs($toolTypes, $items);
        $this->applyWearProfiles();
        $types = $this->seedMaintenanceTypes();
        $this->seedPlans($types);
        $this->seedServiceHistory($types);
        $this->seedTickets($types);
        $this->seedLoanHistory();
        $this->seedOpenRequests();

        $this->command?->info(sprintf(
            'Test data ready: %d items, %d plans, %d work orders, %d tickets, %d loans.',
            Item::query()->count(),
            MaintenancePlan::query()->count(),
            WorkOrder::query()->count(),
            Ticket::query()->count(),
            Loan::query()->count(),
        ));

        unset($items);
    }

    /** @return array<string, User> */
    protected function seedPeople(): array
    {
        $properties = Property::query()->orderBy('id')->get()->values();

        $people = [
            'tina' => ['Tina Nguyen', 'tina@depotborrow.test', 'Depot Technician', 'depot_admin', 0],
            'raj' => ['Raj Patel', 'raj@depotborrow.test', 'Maintenance Tech', 'borrower', 1],
            'sam' => ['Sam Okafor', 'sam@depotborrow.test', 'Groundskeeper', 'borrower', 2],
            'lena' => ['Lena Brooks', 'lena@depotborrow.test', 'Property Manager', 'property_manager', 3],
        ];

        $created = [];
        foreach ($people as $key => [$name, $email, $title, $role, $propertyIndex]) {
            $property = $properties[$propertyIndex] ?? $properties->first();

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'job_title' => $title,
                    'is_active' => true,
                    'default_property_id' => $property?->id,
                ]
            );

            // Fall back to borrower when an optional role was never seeded.
            $user->syncRoles([$this->roleOrBorrower($role)]);

            if ($property) {
                $property->users()->syncWithoutDetaching([
                    $user->id => ['is_default' => true, 'is_active' => true],
                ]);
            }

            $created[$key] = $user;
        }

        $created['mike'] = User::query()->where('email', 'mike@depotborrow.test')->first() ?? $created['tina'];
        $created['joe'] = User::query()->where('email', 'joe@depotborrow.test')->first() ?? $created['raj'];

        return $created;
    }

    protected function roleOrBorrower(string $role): string
    {
        return \Spatie\Permission\Models\Role::query()->where('name', $role)->exists() ? $role : 'borrower';
    }

    /** @return array<string, ToolType> */
    protected function seedExtraCatalog(): array
    {
        $categories = [
            'Power Equipment' => ['icon' => 'generator', 'color' => '#b45309'],
            'Access Equipment' => ['icon' => 'step-ladder', 'color' => '#4338ca'],
            'Cleaning Equipment' => ['icon' => 'carpet-extractor', 'color' => '#0f766e'],
            'Power Tools' => ['icon' => 'drill', 'color' => '#be123c'],
            'Concrete & Demolition' => ['icon' => 'jackhammer', 'color' => '#57534e'],
            'Painting' => ['icon' => 'paint-sprayer', 'color' => '#7c3aed'],
        ];

        $categoryModels = [];
        foreach ($categories as $name => $meta) {
            $categoryModels[$name] = Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                array_merge($meta, ['name' => $name, 'is_active' => true])
            );
        }

        $definitions = [
            ['category' => 'Power Equipment', 'name' => 'Hedge Trimmer', 'icon' => 'hedge-trimmer', 'sku_prefix' => 'HTR', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Power Equipment', 'name' => 'Leaf Blower', 'icon' => 'leaf-blower', 'sku_prefix' => 'BLW', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Power Equipment', 'name' => 'Portable Generator', 'icon' => 'generator', 'sku_prefix' => 'GEN', 'tracks_fuel' => true, 'fuel_type' => 'gasoline', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 10],
            ['category' => 'Access Equipment', 'name' => 'Extension Ladder', 'icon' => 'extension-ladder', 'sku_prefix' => 'LAD', 'default_loan_days' => 5, 'max_loan_days' => 14],
            ['category' => 'Access Equipment', 'name' => 'Step Ladder', 'icon' => 'step-ladder', 'sku_prefix' => 'LADS', 'default_loan_days' => 5, 'max_loan_days' => 14],
            ['category' => 'Cleaning Equipment', 'name' => 'Wet Dry Vacuum', 'icon' => 'shop-vacuum', 'sku_prefix' => 'VAC', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Cleaning Equipment', 'name' => 'Floor Scrubber', 'icon' => 'floor-scrubber', 'sku_prefix' => 'SCR', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Cleaning Equipment', 'name' => 'Carpet Extractor', 'icon' => 'carpet-extractor', 'sku_prefix' => 'CRP', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Cleaning Equipment', 'name' => 'Carpet Dryer', 'icon' => 'air-mover', 'sku_prefix' => 'DRY', 'tracks_usage_hours' => true, 'default_loan_days' => 4, 'max_loan_days' => 10],
            ['category' => 'Power Tools', 'name' => 'Cordless Drill', 'icon' => 'drill', 'sku_prefix' => 'DRL', 'default_loan_days' => 5, 'max_loan_days' => 14],
            ['category' => 'Power Tools', 'name' => 'Circular Saw', 'icon' => 'circular-saw', 'sku_prefix' => 'CSW', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Power Tools', 'name' => 'Reciprocating Saw', 'icon' => 'reciprocating-saw', 'sku_prefix' => 'RSW', 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Concrete & Demolition', 'name' => 'Jackhammer', 'icon' => 'jackhammer', 'sku_prefix' => 'JKH', 'tracks_usage_hours' => true, 'default_loan_days' => 2, 'max_loan_days' => 5],
            ['category' => 'Concrete & Demolition', 'name' => 'Concrete Mixer', 'icon' => 'concrete-mixer', 'sku_prefix' => 'MIX', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
            ['category' => 'Painting', 'name' => 'Airless Paint Sprayer', 'icon' => 'paint-sprayer', 'sku_prefix' => 'SPR', 'tracks_usage_hours' => true, 'default_loan_days' => 3, 'max_loan_days' => 7],
        ];

        $toolTypes = [];
        foreach ($definitions as $definition) {
            $category = $categoryModels[$definition['category']];
            unset($definition['category']);

            $toolTypes[$definition['name']] = ToolType::query()->updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                array_merge($definition, [
                    'category_id' => $category->id,
                    'is_active' => true,
                    'allow_waitlist' => true,
                ])
            );
        }

        return $toolTypes;
    }

    /** @return array<int, Item> */
    protected function seedExtraItems(array $toolTypes): array
    {
        $refs = app(ReferenceGenerator::class);
        $depots = Depot::query()->orderBy('id')->get()->values();
        $main = $depots->first();
        $satellite = $depots->get(1) ?? $main;

        $counts = [
            'Hedge Trimmer' => 3,
            'Leaf Blower' => 3,
            'Portable Generator' => 2,
            'Extension Ladder' => 4,
            'Step Ladder' => 3,
            'Wet Dry Vacuum' => 2,
            'Floor Scrubber' => 1,
            'Carpet Extractor' => 2,
            'Carpet Dryer' => 4,
            'Cordless Drill' => 5,
            'Circular Saw' => 2,
            'Reciprocating Saw' => 2,
            'Jackhammer' => 1,
            'Concrete Mixer' => 1,
            'Airless Paint Sprayer' => 2,
        ];

        $items = [];
        foreach ($counts as $typeName => $count) {
            $toolType = $toolTypes[$typeName];
            $prefix = strtoupper($toolType->sku_prefix ?: 'AST');

            for ($i = 1; $i <= $count; $i++) {
                $assetTag = $prefix.'-DEMO-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $existing = Item::query()->where('asset_tag', $assetTag)->first();

                $items[] = Item::query()->updateOrCreate(
                    ['asset_tag' => $assetTag],
                    [
                        'depot_id' => ($i % 3 === 0 ? $satellite : $main)->id,
                        'home_depot_id' => ($i % 3 === 0 ? $satellite : $main)->id,
                        'tool_type_id' => $toolType->id,
                        'custom_status_id' => $existing?->custom_status_id ?? $this->statuses['available']->id,
                        'name' => $toolType->name.' #'.$i,
                        'condition' => 'good',
                        'is_loanable' => true,
                        'qr_token' => $existing?->qr_token ?? $refs->qrToken(),
                        'numeric_code' => $existing?->numeric_code ?? $refs->numericCode(),
                        'purchase_date' => now()->subYears(1 + ($i % 6))->subDays($i * 23),
                        'purchase_price' => 220 + ($i * 85),
                        'lifespan_years' => 4 + ($i % 4),
                        'salvage_value' => 25 + ($i * 5),
                        'replacement_cost' => 280 + ($i * 95),
                        'warranty_expires_on' => now()->addMonths(6 - ($i % 6)),
                    ]
                );
            }
        }

        return $items;
    }

    /**
     * Spread usage, wear and age so the servicing and budget screens show a mix
     * rather than every tool looking brand new.
     */
    protected function applyWearProfiles(): void
    {
        $profiles = [
            'PW-DEMO-01' => ['usage_hours' => 132, 'lifetime_loan_count' => 24, 'lifetime_fuel_cycles' => 0, 'condition' => 'fair'],
            'PW-DEMO-02' => ['usage_hours' => 61, 'lifetime_loan_count' => 11, 'lifetime_fuel_cycles' => 0, 'condition' => 'good'],
            'PWG-DEMO-01' => ['usage_hours' => 218, 'lifetime_loan_count' => 39, 'lifetime_fuel_cycles' => 14, 'condition' => 'poor', 'end_of_life_soon' => true],
            'PWG-DEMO-02' => ['usage_hours' => 96, 'lifetime_loan_count' => 17, 'lifetime_fuel_cycles' => 6, 'condition' => 'good'],
            'MOW-DEMO-01' => ['usage_hours' => 187, 'lifetime_loan_count' => 46, 'lifetime_fuel_cycles' => 21, 'condition' => 'fair'],
            'MOW-DEMO-02' => ['usage_hours' => 74, 'lifetime_loan_count' => 19, 'lifetime_fuel_cycles' => 8, 'condition' => 'good'],
            'MOW-DEMO-03' => ['usage_hours' => 41, 'lifetime_loan_count' => 9, 'lifetime_fuel_cycles' => 3, 'condition' => 'good'],
            'RMOW-DEMO-01' => ['usage_hours' => 340, 'lifetime_loan_count' => 52, 'lifetime_fuel_cycles' => 27, 'condition' => 'fair'],
            'HTR-DEMO-01' => ['usage_hours' => 58, 'lifetime_loan_count' => 22, 'lifetime_fuel_cycles' => 9, 'condition' => 'good'],
            'BLW-DEMO-01' => ['usage_hours' => 143, 'lifetime_loan_count' => 33, 'lifetime_fuel_cycles' => 16, 'condition' => 'poor'],
            'GEN-DEMO-01' => ['usage_hours' => 265, 'lifetime_loan_count' => 14, 'lifetime_fuel_cycles' => 19, 'condition' => 'fair', 'end_of_life_soon' => true],
            'VAC-DEMO-01' => ['usage_hours' => 88, 'lifetime_loan_count' => 27, 'lifetime_fuel_cycles' => 0, 'condition' => 'good'],
            'SCR-DEMO-01' => ['usage_hours' => 121, 'lifetime_loan_count' => 15, 'lifetime_fuel_cycles' => 0, 'condition' => 'fair'],
        ];

        foreach ($profiles as $assetTag => $attributes) {
            Item::query()->where('asset_tag', $assetTag)->first()?->update($attributes);
        }
    }

    /** @return array<string, MaintenanceType> */
    protected function seedMaintenanceTypes(): array
    {
        $definitions = [
            'oil-change' => ['name' => 'Oil Change', 'kind' => 'preventive', 'requires_downtime' => true],
            'blade-sharpen' => ['name' => 'Blade Sharpening', 'kind' => 'preventive', 'requires_downtime' => false],
            'gasket-inspect' => ['name' => 'Gasket Inspection', 'kind' => 'inspection', 'requires_downtime' => false],
            'air-filter' => ['name' => 'Air Filter Replacement', 'kind' => 'preventive', 'requires_downtime' => false],
            'spark-plug' => ['name' => 'Spark Plug Change', 'kind' => 'preventive', 'requires_downtime' => true],
            'belt-replace' => ['name' => 'Drive Belt Replacement', 'kind' => 'corrective', 'requires_downtime' => true],
            'winterise' => ['name' => 'Winter Storage Prep', 'kind' => 'preventive', 'requires_downtime' => false],
            'safety-check' => ['name' => 'Annual Safety Check', 'kind' => 'inspection', 'requires_downtime' => false],
        ];

        $types = [];
        foreach ($definitions as $slug => $definition) {
            $types[$slug] = MaintenanceType::query()->updateOrCreate(
                ['slug' => $slug],
                $definition + ['is_active' => true]
            );
        }

        return $types;
    }

    /**
     * One plan of every flavour: overdue and blocking, overdue but warn-only,
     * due in a few days, and comfortably on track.
     */
    protected function seedPlans(array $types): void
    {
        $plans = [
            // Overdue + blocks pick-up.
            ['PWG-DEMO-01', 'oil-change', 'Oil change (overdue)', 'usage_hours', ['interval_hours' => 50, 'next_due_hours' => 200], true],
            ['RMOW-DEMO-01', 'belt-replace', 'Drive belt (overdue)', 'usage_hours', ['interval_hours' => 100, 'next_due_hours' => 300], true],
            // Overdue but warn only.
            ['MOW-DEMO-01', 'blade-sharpen', 'Sharpen blades (overdue)', 'loan_count', ['interval_loans' => 15, 'next_due_loans' => 45], false],
            ['BLW-DEMO-01', 'air-filter', 'Air filter (overdue)', 'fuel_cycles', ['interval_fuel_cycles' => 10, 'next_due_fuel_cycles' => 15], false],
            ['GEN-DEMO-01', 'safety-check', 'Safety check (overdue)', 'calendar', ['interval_days' => 365, 'next_due_at' => now()->subDays(26)], false],
            // Due soon.
            ['PW-DEMO-01', 'gasket-inspect', 'Gasket check (due soon)', 'calendar', ['interval_days' => 180, 'next_due_at' => now()->addDays(4)], false],
            ['MOW-DEMO-02', 'spark-plug', 'Spark plug (due soon)', 'usage_hours', ['interval_hours' => 80, 'next_due_hours' => 78], false],
            // On track.
            ['PW-DEMO-02', 'oil-change', 'Oil change', 'usage_hours', ['interval_hours' => 50, 'next_due_hours' => 110], true],
            ['HTR-DEMO-01', 'air-filter', 'Air filter', 'fuel_cycles', ['interval_fuel_cycles' => 12, 'next_due_fuel_cycles' => 20], false],
            ['VAC-DEMO-01', 'safety-check', 'Safety check', 'calendar', ['interval_days' => 365, 'next_due_at' => now()->addMonths(7)], false],
            ['SCR-DEMO-01', 'winterise', 'Winter prep', 'calendar', ['interval_days' => 365, 'next_due_at' => now()->addMonths(5)], false],
            ['MOW-DEMO-03', 'blade-sharpen', 'Sharpen blades', 'loan_count', ['interval_loans' => 15, 'next_due_loans' => 24], false],
        ];

        foreach ($plans as [$assetTag, $typeSlug, $name, $trigger, $schedule, $blocks]) {
            $item = Item::query()->where('asset_tag', $assetTag)->first();
            if (! $item || ! isset($types[$typeSlug])) {
                continue;
            }

            MaintenancePlan::query()->updateOrCreate(
                ['item_id' => $item->id, 'maintenance_type_id' => $types[$typeSlug]->id, 'name' => $name],
                array_merge($schedule, [
                    'trigger_type' => $trigger,
                    'blocks_checkout_when_overdue' => $blocks,
                    'is_active' => true,
                    'last_performed_at' => now()->subMonths(4),
                ])
            );
        }
    }

    /**
     * Completed work orders give the budget screen real repair spend, and the
     * open ones give the servicing screen something to work through.
     */
    protected function seedServiceHistory(array $types): void
    {
        $tech = $this->users['tina'];
        $history = [
            // Heavy repair spend so CapEx flags "replace sooner".
            ['WO-TEST-01', 'PWG-DEMO-01', 'oil-change', 'Oil and filter change', 'completed', 120, 65, 3.0, 5],
            ['WO-TEST-02', 'PWG-DEMO-01', 'belt-replace', 'Pump rebuild after seal failure', 'completed', 480, 310, 6.5, 3],
            ['WO-TEST-03', 'RMOW-DEMO-01', 'belt-replace', 'Drive belt and idler pulley', 'completed', 265, 180, 4.0, 2],
            ['WO-TEST-04', 'MOW-DEMO-01', 'blade-sharpen', 'Sharpen and balance blades', 'completed', 45, 0, 1.5, 1],
            ['WO-TEST-05', 'GEN-DEMO-01', 'safety-check', 'Annual safety inspection', 'completed', 90, 0, 2.0, 8],
            ['WO-TEST-06', 'BLW-DEMO-01', 'air-filter', 'Air filter and fuel line', 'completed', 55, 30, 1.0, 4],
            // Still open.
            ['WO-TEST-07', 'RMOW-DEMO-01', 'oil-change', 'Oil change due before next loan', 'open', null, null, null, null],
            ['WO-TEST-08', 'MOW-DEMO-01', 'spark-plug', 'Hard starting — replace plug', 'in_progress', null, null, null, null],
            ['WO-TEST-09', 'SCR-DEMO-01', 'winterise', 'Winter storage prep', 'open', null, null, null, null],
        ];

        foreach ($history as [$reference, $assetTag, $typeSlug, $title, $status, $total, $parts, $hours, $monthsAgo]) {
            $item = Item::query()->where('asset_tag', $assetTag)->first();
            if (! $item) {
                continue;
            }

            $plan = MaintenancePlan::query()
                ->where('item_id', $item->id)
                ->where('maintenance_type_id', $types[$typeSlug]->id ?? 0)
                ->first();

            WorkOrder::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'item_id' => $item->id,
                    'maintenance_type_id' => $types[$typeSlug]->id ?? null,
                    'maintenance_plan_id' => $plan?->id,
                    'title' => $title,
                    'description' => $title.' logged for '.$item->displayName().'.',
                    'status' => $status,
                    'priority' => $status === 'in_progress' ? 'high' : 'normal',
                    'assigned_to' => $tech->id,
                    'scheduled_start_at' => $monthsAgo ? now()->subMonths($monthsAgo) : now()->addDays(3),
                    'completed_at' => $status === 'completed' ? now()->subMonths($monthsAgo) : null,
                    'completed_by' => $status === 'completed' ? $tech->id : null,
                    'labour_hours' => $hours,
                    'parts_cost' => $parts,
                    'total_cost' => $total,
                    'completion_notes' => $status === 'completed' ? 'Work finished and tool tested.' : null,
                    'parts_used' => $parts ? 'See invoice' : null,
                ]
            );
        }
    }

    protected function seedTickets(array $types): void
    {
        $tickets = [
            ['TK-TEST-01', 'PWG-DEMO-01', 'defect', 'Leaking high pressure hose', 'critical', 'open', true, null],
            ['TK-TEST-02', 'BLW-DEMO-01', 'defect', 'Throttle sticks when warm', 'high', 'in_progress', false, null],
            ['TK-TEST-03', 'MOW-DEMO-01', 'damage', 'Deck dented after transport', 'medium', 'open', false, null],
            ['TK-TEST-04', 'LAD-DEMO-02', 'damage', 'Bent rung near the top', 'critical', 'open', true, null],
            ['TK-TEST-05', 'PW-DEMO-01', 'defect', 'Trigger needed replacing', 'low', 'resolved', false, 'REPAIRED'],
            ['TK-TEST-06', 'VAC-DEMO-01', 'inspection', 'Filter clogged at check-in', 'low', 'resolved', false, 'CLEANED'],
        ];

        foreach ($tickets as [$reference, $assetTag, $type, $title, $severity, $status, $takesOut, $resolutionCode]) {
            $item = Item::query()->where('asset_tag', $assetTag)->first();
            if (! $item) {
                continue;
            }

            $ticket = Ticket::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'item_id' => $item->id,
                    'ticket_type' => $type,
                    'title' => $title,
                    'description' => $title.' — reported from the field.',
                    'severity' => $severity,
                    'priority' => $severity === 'critical' ? 'urgent' : 'normal',
                    'status' => $status,
                    'takes_out_of_service' => $takesOut,
                    'reported_by' => $this->users['joe']->id,
                    'assigned_to' => $this->users['tina']->id,
                    'resolved_at' => $status === 'resolved' ? now()->subWeeks(2) : null,
                    'resolved_by' => $status === 'resolved' ? $this->users['tina']->id : null,
                    'resolution_code' => $resolutionCode,
                    'resolution_notes' => $status === 'resolved' ? 'Fixed and returned to stock.' : null,
                    'total_cost' => $status === 'resolved' ? 65 : null,
                ]
            );

            // Unsafe tools really must leave circulation.
            if ($takesOut && $status !== 'resolved' && isset($this->statuses['out-of-service'])) {
                $item->update(['custom_status_id' => $this->statuses['out-of-service']->id]);
            }

            unset($ticket);
        }
    }

    /**
     * Loans in every state: finished with inspections, out now, overdue,
     * waiting on depot inspection, and ready for pick-up.
     */
    protected function seedLoanHistory(): void
    {
        $property = Property::query()->orderBy('id')->first();
        $depot = Depot::query()->orderBy('id')->first();

        $loans = [
            ['LN-TEST-01', 'MOW-DEMO-02', 'closed', 'raj', -60, -53, true],
            ['LN-TEST-02', 'PW-DEMO-02', 'closed', 'sam', -35, -30, true],
            ['LN-TEST-03', 'VAC-DEMO-01', 'closed', 'joe', -14, -9, true],
            ['LN-TEST-04', 'HTR-DEMO-01', 'checked_out', 'raj', -3, 4, false],
            ['LN-TEST-05', 'BLW-DEMO-02', 'checked_out', 'sam', -12, -2, false],
            ['LN-TEST-06', 'GEN-DEMO-02', 'return_pending', 'joe', -8, -1, false],
            ['LN-TEST-07', 'LAD-DEMO-01', 'reserved', 'sam', 1, 6, false],
        ];

        foreach ($loans as [$reference, $assetTag, $status, $userKey, $startDays, $dueDays, $returned]) {
            $item = Item::query()->where('asset_tag', $assetTag)->first();
            $borrower = $this->users[$userKey] ?? null;

            if (! $item || ! $borrower || ! $property || ! $depot) {
                continue;
            }

            $loan = Loan::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'property_id' => $property->id,
                    'borrower_id' => $borrower->id,
                    'depot_id' => $depot->id,
                    'status' => $status,
                    'reserved_at' => now()->addDays($startDays)->subDay(),
                    'checked_out_at' => $status === 'reserved' ? null : now()->addDays($startDays),
                    'checked_out_by' => $status === 'reserved' ? null : $this->users['mike']->id,
                    'due_at' => now()->addDays($dueDays),
                    'original_due_at' => now()->addDays($dueDays),
                    'return_requested_at' => in_array($status, ['return_pending', 'closed'], true) ? now()->addDays($dueDays)->subHours(3) : null,
                    'returned_at' => $returned ? now()->addDays($dueDays) : null,
                    'received_by' => $returned ? $this->users['mike']->id : null,
                    'closed_at' => $status === 'closed' ? now()->addDays($dueDays) : null,
                ]
            );

            $hoursOut = max(0, (float) $item->usage_hours - 6);
            $loanItem = LoanItem::query()->updateOrCreate(
                ['loan_id' => $loan->id, 'item_id' => $item->id],
                [
                    'quantity' => 1,
                    'status' => $returned ? 'returned' : ($status === 'reserved' ? 'reserved' : 'checked_out'),
                    'checked_out_at' => $status === 'reserved' ? null : now()->addDays($startDays),
                    'condition_out' => 'good',
                    'fuel_pct_out' => 90,
                    'usage_hours_out' => $hoursOut,
                    'returned_at' => $returned ? now()->addDays($dueDays) : null,
                    'condition_in' => $returned ? 'good' : null,
                    'fuel_pct_in' => $returned ? 55 : null,
                    'usage_hours_in' => $returned ? (float) $item->usage_hours : null,
                    'usage_hours_delta' => $returned ? 6 : null,
                ]
            );

            if ($returned) {
                ReturnInspection::query()->updateOrCreate(
                    ['loan_item_id' => $loanItem->id],
                    [
                        'loan_id' => $loan->id,
                        'item_id' => $item->id,
                        'inspected_by' => $this->users['mike']->id,
                        'is_self_return' => false,
                        'admin_reviewed' => true,
                        'inspected_at' => now()->addDays($dueDays),
                        'overall_result' => 'pass',
                        'condition' => 'good',
                        'fuel_pct' => 55,
                        'usage_hours_estimate' => 6,
                        'usage_hours_reading' => (float) $item->usage_hours,
                        'damage_found' => false,
                        'notes' => 'Returned clean and working.',
                    ]
                );
            }

            $this->syncItemStatusForLoan($item, $status);
        }
    }

    protected function syncItemStatusForLoan(Item $item, string $loanStatus): void
    {
        $slug = match ($loanStatus) {
            'reserved' => 'reserved',
            'checked_out', 'return_pending' => 'checked-out',
            default => null,
        };

        if ($slug && isset($this->statuses[$slug])) {
            $item->update(['custom_status_id' => $this->statuses[$slug]->id]);
        }
    }

    /** Requests waiting in the approvals queue plus a draft in someone's cart. */
    protected function seedOpenRequests(): void
    {
        $borrowService = app(BorrowService::class);
        $property = Property::query()->orderBy('id')->first();
        $depot = Depot::query()->orderBy('id')->first();

        if (! $property || ! $depot) {
            return;
        }

        $queue = [
            ['raj', 'HTR-DEMO-02', 'Trim hedges along the front walk', 'normal', true],
            ['sam', 'LAD-DEMO-03', 'Replace hallway light fittings', 'high', true],
            ['joe', 'VAC-DEMO-02', 'Clean up after carpet repair', 'normal', true],
            ['raj', 'SHV-DEMO-02', 'Dig out the irrigation line', 'low', false],
        ];

        foreach ($queue as [$userKey, $assetTag, $purpose, $priority, $submit]) {
            $item = Item::query()->where('asset_tag', $assetTag)->first();
            $user = $this->users[$userKey] ?? null;

            if (! $item || ! $user || ! $item->isAvailableForBorrow()) {
                continue;
            }

            $exists = \App\Models\BorrowRequest::query()
                ->where('requester_id', $user->id)
                ->where('purpose', $purpose)
                ->exists();

            if ($exists) {
                continue;
            }

            $draft = $borrowService->createDraft($user, [
                'property_id' => $property->id,
                'pickup_depot_id' => $depot->id,
                'priority' => $priority,
                'purpose' => $purpose,
                'needed_from' => now()->addDays(1),
                'needed_until' => now()->addDays(4),
                'lines' => [
                    ['request_mode' => 'specific_item', 'item_id' => $item->id, 'quantity' => 1],
                ],
            ]);

            if ($submit) {
                $borrowService->submit($draft);
            }
        }
    }
}
