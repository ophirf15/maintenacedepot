<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'custom_field_id', 'fieldable_type', 'fieldable_id',
        'value_text', 'value_number', 'value_bool', 'value_date', 'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_bool' => 'boolean',
            'value_date' => 'datetime',
            'value_json' => 'array',
            'value_number' => 'decimal:4',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }
}
