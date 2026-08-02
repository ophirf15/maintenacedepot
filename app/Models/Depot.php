<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depot extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id', 'parent_depot_id', 'name', 'code', 'type',
        'is_pickup_point', 'is_return_point', 'address_line1', 'city', 'phone', 'notes',
        'allow_cross_property_transfer', 'default_max_loan_days', 'pickup_window_enabled',
        'pickup_window_hours', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_pickup_point' => 'boolean',
            'is_return_point' => 'boolean',
            'allow_cross_property_transfer' => 'boolean',
            'pickup_window_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'parent_depot_id');
    }

    public function satellites(): HasMany
    {
        return $this->hasMany(Depot::class, 'parent_depot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
