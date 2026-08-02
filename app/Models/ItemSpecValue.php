<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSpecValue extends Model
{
    protected $fillable = [
        'item_id', 'tool_type_spec_field_id', 'value',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ToolTypeSpecField::class, 'tool_type_spec_field_id');
    }
}
