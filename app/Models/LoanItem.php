<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanItem extends Model
{
    protected $fillable = [
        'loan_id', 'item_id', 'borrow_request_line_id', 'companion_of_loan_item_id',
        'quantity', 'status',
        'checked_out_at', 'condition_out', 'fuel_pct_out', 'usage_hours_out',
        'returned_at', 'condition_in', 'fuel_pct_in', 'usage_hours_in',
        'usage_hours_delta', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
            'usage_hours_out' => 'decimal:2',
            'usage_hours_in' => 'decimal:2',
            'usage_hours_delta' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function companionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'companion_of_loan_item_id');
    }

    public function companions(): HasMany
    {
        return $this->hasMany(self::class, 'companion_of_loan_item_id');
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(ReturnInspection::class);
    }
}
