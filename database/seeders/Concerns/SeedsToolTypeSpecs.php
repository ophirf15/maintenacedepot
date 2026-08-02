<?php

namespace Database\Seeders\Concerns;

use App\Models\Item;
use App\Models\ItemSpecValue;
use App\Models\ToolType;
use App\Models\ToolTypeSpecField;
use Illuminate\Support\Collection;

trait SeedsToolTypeSpecs
{
    /**
     * Spec field templates + deterministic per-unit values for every known tool type.
     *
     * @param  array<string, ToolType>|Collection<string, ToolType>  $toolTypes
     * @param  iterable<int, Item>|null  $items  optional; falls back to DB for each type
     */
    protected function seedAllToolTypeSpecs(array|Collection $toolTypes, ?iterable $items = null): void
    {
        $byName = $toolTypes instanceof Collection
            ? $toolTypes
            : collect($toolTypes);

        // Also pick up types created by other seeders (e.g. TestDataSeeder ladders).
        foreach (ToolType::query()->get() as $type) {
            if (! $byName->has($type->name)) {
                $byName[$type->name] = $type;
            }
        }

        $catalog = $this->specCatalog();

        foreach ($catalog as $typeName => $fields) {
            $type = $byName->get($typeName)
                ?? ToolType::query()->where('name', $typeName)->first();

            if (! $type) {
                continue;
            }

            $fieldModels = [];
            foreach ($fields as $index => $field) {
                $fieldModels[$field['key']] = $this->ensureSpecField(
                    $type,
                    $field['key'],
                    $field['label'],
                    $field['unit'] ?? null,
                    $field['field_type'] ?? 'text',
                    $index + 1,
                );
            }

            $typeItems = $this->itemsForType($type, $items);
            foreach ($typeItems->values() as $i => $item) {
                $n = $this->unitIndex($item, $i);
                foreach ($fields as $field) {
                    $value = ($field['value'])($n, $item);
                    if ($value === null || $value === '') {
                        continue;
                    }
                    ItemSpecValue::query()->updateOrCreate(
                        [
                            'item_id' => $item->id,
                            'tool_type_spec_field_id' => $fieldModels[$field['key']]->id,
                        ],
                        ['value' => (string) $value]
                    );
                }
            }
        }
    }

    /**
     * @return array<string, list<array{key: string, label: string, unit: ?string, field_type: string, value: callable}>>
     */
    protected function specCatalog(): array
    {
        return [
            'Electric Pressure Washer' => [
                $this->spec('psi', 'PSI', 'psi', 'number', fn (int $n) => (string) [1500, 2000, 3000][($n - 1) % 3]),
                $this->spec('gpm', 'GPM', 'gpm', 'number', fn (int $n) => ['1.4', '1.6', '2.0'][($n - 1) % 3]),
            ],
            'Gas Pressure Washer' => [
                $this->spec('psi', 'PSI', 'psi', 'number', fn (int $n) => (string) [2700, 3200, 4000][($n - 1) % 3]),
                $this->spec('gpm', 'GPM', 'gpm', 'number', fn (int $n) => ['2.3', '2.5', '2.8'][($n - 1) % 3]),
            ],
            'Push Mower' => [
                $this->spec('deck_width_in', 'Deck width', 'in', 'number', fn (int $n) => (string) [21, 22, 30][($n - 1) % 3]),
                $this->spec('engine_cc', 'Engine', 'cc', 'number', fn (int $n) => (string) [140, 160, 190][($n - 1) % 3]),
            ],
            'Riding Mower' => [
                $this->spec('deck_width_in', 'Deck width', 'in', 'number', fn (int $n) => (string) [42, 48, 54][($n - 1) % 3]),
                $this->spec('engine_hp', 'Engine', 'hp', 'number', fn (int $n) => (string) [18, 22, 24][($n - 1) % 3]),
            ],
            'Round Point Shovel' => [
                $this->spec('handle_length_in', 'Handle', 'in', 'number', fn (int $n) => (string) [48, 54, 60][($n - 1) % 3]),
                $this->spec('material', 'Material', null, 'text', fn (int $n) => ['fiberglass', 'ash wood', 'steel'][($n - 1) % 3]),
            ],
            'Square Point Shovel' => [
                $this->spec('handle_length_in', 'Handle', 'in', 'number', fn (int $n) => (string) [48, 54, 60][($n - 1) % 3]),
                $this->spec('material', 'Material', null, 'text', fn (int $n) => ['fiberglass', 'ash wood', 'steel'][($n - 1) % 3]),
            ],
            'Hedge Trimmer' => [
                $this->spec('blade_length_in', 'Blade', 'in', 'number', fn (int $n) => (string) [18, 22, 24][($n - 1) % 3]),
                $this->spec('power', 'Power', null, 'text', fn (int $n) => ['gas', 'cordless 40V', 'corded'][($n - 1) % 3]),
            ],
            'Leaf Blower' => [
                $this->spec('cfm', 'Airflow', 'CFM', 'number', fn (int $n) => (string) [400, 550, 700][($n - 1) % 3]),
                $this->spec('power', 'Power', null, 'text', fn (int $n) => ['gas backpack', 'cordless', 'corded'][($n - 1) % 3]),
            ],
            'Portable Generator' => [
                $this->spec('watts', 'Running watts', 'W', 'number', fn (int $n) => (string) [3000, 4500, 7000][($n - 1) % 3]),
                $this->spec('fuel', 'Fuel', null, 'text', fn () => 'gasoline'),
            ],
            'Extension Ladder' => [
                $this->spec('height_ft', 'Height', 'ft', 'number', fn (int $n) => (string) (16 + (($n - 1) % 3) * 8)),
                $this->spec('duty_rating', 'Duty rating', null, 'text', fn (int $n) => ['Type II', 'Type I', 'Type IA'][($n - 1) % 3]),
            ],
            'Step Ladder' => [
                $this->spec('height_ft', 'Height', 'ft', 'number', fn (int $n) => (string) [4, 6, 8][($n - 1) % 3]),
                $this->spec('material', 'Material', null, 'text', fn (int $n) => ['aluminum', 'fiberglass'][($n - 1) % 2]),
            ],
            'Wet Dry Vacuum' => [
                $this->spec('tank_gal', 'Tank', 'gal', 'number', fn (int $n) => (string) [6, 10, 16][($n - 1) % 3]),
                $this->spec('peak_hp', 'Peak HP', 'hp', 'number', fn (int $n) => ['4.5', '5.0', '6.5'][($n - 1) % 3]),
            ],
            'Floor Scrubber' => [
                $this->spec('path_width_in', 'Path width', 'in', 'number', fn (int $n) => (string) [17, 20, 26][($n - 1) % 3]),
                $this->spec('tank_gal', 'Solution tank', 'gal', 'number', fn (int $n) => (string) [10, 12, 16][($n - 1) % 3]),
            ],
            'Carpet Extractor' => [
                $this->spec('tank_gal', 'Solution tank', 'gal', 'number', fn (int $n) => (string) [2, 3, 5][($n - 1) % 3]),
                $this->spec('heat', 'Heat', null, 'text', fn (int $n) => ['none', 'onboard heater'][($n - 1) % 2]),
            ],
            'Carpet Dryer' => [
                $this->spec('cfm', 'Airflow', 'CFM', 'number', fn (int $n) => (string) [800, 1000, 1600][($n - 1) % 3]),
                $this->spec('speeds', 'Speeds', null, 'number', fn (int $n) => (string) [1, 3][($n - 1) % 2]),
            ],
            'Cordless Drill' => [
                $this->spec('voltage', 'Battery', 'V', 'number', fn (int $n) => (string) [12, 18, 20][($n - 1) % 3]),
                $this->spec('chuck_in', 'Chuck', 'in', 'text', fn (int $n) => ['1/4', '3/8', '1/2'][($n - 1) % 3]),
            ],
            'Circular Saw' => [
                $this->spec('blade_in', 'Blade', 'in', 'number', fn (int $n) => ['6.5', '7.25'][($n - 1) % 2]),
                $this->spec('power', 'Power', null, 'text', fn (int $n) => ['cordless 18V', 'corded'][($n - 1) % 2]),
            ],
            'Reciprocating Saw' => [
                $this->spec('stroke_in', 'Stroke', 'in', 'text', fn (int $n) => ['7/8', '1-1/8'][($n - 1) % 2]),
                $this->spec('power', 'Power', null, 'text', fn (int $n) => ['cordless 18V', 'corded'][($n - 1) % 2]),
            ],
            'Jackhammer' => [
                $this->spec('weight_lb', 'Weight', 'lb', 'number', fn (int $n) => (string) [30, 45, 60][($n - 1) % 3]),
                $this->spec('power', 'Power', null, 'text', fn (int $n) => ['electric', 'pneumatic'][($n - 1) % 2]),
            ],
            'Concrete Mixer' => [
                $this->spec('drum_cu_ft', 'Drum', 'cu ft', 'number', fn (int $n) => ['3.5', '6', '9'][($n - 1) % 3]),
                $this->spec('power', 'Power', null, 'text', fn () => 'electric'),
            ],
            'Airless Paint Sprayer' => [
                $this->spec('max_psi', 'Max PSI', 'psi', 'number', fn (int $n) => (string) [2000, 3000, 3300][($n - 1) % 3]),
                $this->spec('gpm', 'Flow', 'gpm', 'number', fn (int $n) => ['0.33', '0.47', '0.54'][($n - 1) % 3]),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, unit: ?string, field_type: string, value: callable}
     */
    protected function spec(string $key, string $label, ?string $unit, string $fieldType, callable $value): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'field_type' => $fieldType,
            'value' => $value,
        ];
    }

    protected function ensureSpecField(
        ToolType $toolType,
        string $key,
        string $label,
        ?string $unit,
        string $fieldType,
        int $sortOrder,
    ): ToolTypeSpecField {
        return ToolTypeSpecField::query()->updateOrCreate(
            ['tool_type_id' => $toolType->id, 'key' => $key],
            [
                'label' => $label,
                'unit' => $unit,
                'field_type' => $fieldType,
                'sort_order' => $sortOrder,
                'is_filterable' => true,
            ]
        );
    }

    /** @param  iterable<int, Item>|null  $items */
    protected function itemsForType(ToolType $type, ?iterable $items): Collection
    {
        if ($items !== null) {
            $matched = collect($items)->filter(fn (Item $item) => $item->tool_type_id === $type->id)->values();
            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        return Item::query()->where('tool_type_id', $type->id)->orderBy('asset_tag')->get();
    }

    protected function unitIndex(Item $item, int $fallbackIndex): int
    {
        $digits = preg_replace('/\D+/', '', $item->asset_tag ?? '');

        return max(1, (int) ($digits !== '' ? $digits : ($fallbackIndex + 1)));
    }
}
