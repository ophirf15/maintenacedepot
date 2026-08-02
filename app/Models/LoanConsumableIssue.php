<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanConsumableIssue extends Model
{
    protected $fillable = [
        'loan_id',
        'item_id',
        'companion_of_loan_item_id',
        'qty_estimated',
        'qty_used',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_estimated' => 'decimal:2',
            'qty_used' => 'decimal:2',
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
        return $this->belongsTo(LoanItem::class, 'companion_of_loan_item_id');
    }
}
