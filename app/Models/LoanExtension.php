<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExtension extends Model
{
    protected $fillable = [
        'loan_id', 'requested_by', 'previous_due_at', 'requested_due_at',
        'approved_due_at', 'status', 'reason', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_due_at' => 'datetime',
            'requested_due_at' => 'datetime',
            'approved_due_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
