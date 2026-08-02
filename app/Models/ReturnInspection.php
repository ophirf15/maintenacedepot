<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnInspection extends Model
{
    protected $fillable = [
        'loan_id', 'loan_item_id', 'item_id', 'inspected_by', 'is_self_return',
        'admin_reviewed', 'inspected_at', 'overall_result', 'condition', 'fuel_pct',
        'usage_hours_estimate', 'usage_hours_reading', 'damage_found', 'damage_description', 'end_of_life_soon',
        'ticket_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_self_return' => 'boolean',
            'admin_reviewed' => 'boolean',
            'inspected_at' => 'datetime',
            'usage_hours_estimate' => 'decimal:2',
            'usage_hours_reading' => 'decimal:2',
            'damage_found' => 'boolean',
            'end_of_life_soon' => 'boolean',
        ];
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
