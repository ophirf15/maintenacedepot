<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolTypeSpecField extends Model
{
    protected $fillable = [
        'tool_type_id', 'key', 'label', 'unit', 'field_type',
        'options', 'sort_order', 'is_filterable',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_filterable' => 'boolean',
        ];
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ItemSpecValue::class);
    }
}
