<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tool_type_id', 'name', 'context', 'is_required', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
