<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $appends = ['label', 'image_url', 'specs', 'numeric_id'];

    protected $fillable = [
        'depot_id', 'home_depot_id', 'tool_type_id', 'custom_status_id', 'parent_item_id',
        'current_property_id', 'is_kit', 'asset_tag', 'numeric_code', 'serial_number', 'qr_token', 'name',
        'description', 'image_path', 'manual_path', 'condition', 'is_consumable', 'stock_qty',
        'reorder_point', 'reorder_qty', 'stock_unit',
        'supplier_name', 'supplier_part_number', 'typical_cost',
        'is_loanable', 'usage_hours', 'lifetime_loan_count', 'lifetime_fuel_cycles', 'fuel_pct',
        'purchase_date', 'purchase_price', 'currency',
        'warranty_expires_on', 'lifespan_years', 'salvage_value', 'replacement_cost',
        'end_of_life_soon', 'location_note', 'metadata', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_kit' => 'boolean',
            'is_consumable' => 'boolean',
            'is_loanable' => 'boolean',
            'end_of_life_soon' => 'boolean',
            'stock_qty' => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'reorder_qty' => 'decimal:2',
            'typical_cost' => 'decimal:2',
            'usage_hours' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'replacement_cost' => 'decimal:2',
            'purchase_date' => 'date',
            'warranty_expires_on' => 'date',
            'metadata' => 'array',
        ];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function homeDepot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'home_depot_id');
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CustomStatus::class, 'custom_status_id');
    }

    public function currentProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'current_property_id');
    }

    public function linkedChildren(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_links', 'parent_item_id', 'child_item_id')
            ->withPivot(['is_required', 'role'])
            ->withTimestamps();
    }

    public function linkedParents(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_links', 'child_item_id', 'parent_item_id')
            ->withPivot(['is_required', 'role'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function specValues(): HasMany
    {
        return $this->hasMany(ItemSpecValue::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        // Avoid a lazy load per row when rendering long item lists.
        $typeName = $this->relationLoaded('toolType') ? $this->toolType?->name : null;

        return trim(($typeName ?: 'Item').' '.$this->asset_tag);
    }

    public function getLabelAttribute(): string
    {
        return $this->displayName();
    }

    /**
     * Unique 6-digit tool number printed under the barcode for keypad entry.
     */
    public function getNumericIdAttribute(): ?string
    {
        return $this->numeric_code;
    }

    public function getImageUrlAttribute(): ?string
    {
        return PublicStorageUrl::path($this->image_path);
    }

    /**
     * Spec chips for catalog/approval UIs: [{key, label, value, unit, display}].
     *
     * @return list<array{key: string, label: string, value: string, unit: ?string, display: string}>
     */
    public function getSpecsAttribute(): array
    {
        if (! $this->relationLoaded('specValues')) {
            return [];
        }

        return $this->specValues
            ->filter(fn (ItemSpecValue $row) => $row->relationLoaded('field') && $row->field)
            ->sortBy(fn (ItemSpecValue $row) => $row->field->sort_order)
            ->values()
            ->map(function (ItemSpecValue $row) {
                $unit = $row->field->unit;
                $display = $unit ? trim($row->value.' '.$unit) : $row->value;

                return [
                    'key' => $row->field->key,
                    'label' => $row->field->label,
                    'value' => $row->value,
                    'unit' => $unit,
                    'display' => $display,
                ];
            })
            ->all();
    }

    public function isAvailableForBorrow(): bool
    {
        return $this->is_loanable
            && $this->status?->availability_effect === 'available'
            && ! in_array($this->status?->slug, ['reserved', 'checked-out', 'out-of-service', 'in-maintenance'], true);
    }
}
