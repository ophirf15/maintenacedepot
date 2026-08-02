<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku_prefix', 'description', 'icon', 'image_path',
        'is_consumable', 'manufacturer', 'model', 'default_loan_days', 'max_loan_days',
        'tracks_fuel', 'fuel_type', 'tracks_usage_hours', 'allow_waitlist', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_consumable' => 'boolean',
            'tracks_fuel' => 'boolean',
            'tracks_usage_hours' => 'boolean',
            'allow_waitlist' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function companionLinks(): HasMany
    {
        return $this->hasMany(ToolTypeLink::class, 'parent_tool_type_id');
    }

    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class);
    }

    public function specFields(): HasMany
    {
        return $this->hasMany(ToolTypeSpecField::class)->orderBy('sort_order')->orderBy('id');
    }
}
