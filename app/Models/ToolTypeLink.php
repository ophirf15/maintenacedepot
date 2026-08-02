<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolTypeLink extends Model
{
    protected $fillable = [
        'parent_tool_type_id',
        'child_tool_type_id',
        'role',
        'is_required',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function parentType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class, 'parent_tool_type_id');
    }

    public function childType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class, 'child_tool_type_id');
    }
}
