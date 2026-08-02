<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'delta',
        'balance_after',
        'reason',
        'loan_id',
        'loan_consumable_issue_id',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(LoanConsumableIssue::class, 'loan_consumable_issue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
