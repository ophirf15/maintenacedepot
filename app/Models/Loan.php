<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use SoftDeletes;

    protected $appends = ['summary', 'items_label'];

    protected $fillable = [
        'reference', 'property_id', 'borrow_request_id', 'borrower_id', 'depot_id', 'status',
        'reserved_at', 'checked_out_at', 'checked_out_by', 'due_at', 'original_due_at',
        'extension_count', 'return_requested_at', 'returned_at', 'received_by', 'closed_at',
        'checkout_notes', 'return_notes', 'damage_reported',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'original_due_at' => 'datetime',
            'return_requested_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
            'damage_reported' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function consumableIssues(): HasMany
    {
        return $this->hasMany(LoanConsumableIssue::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(LoanExtension::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast()
            && in_array($this->status, ['checked_out', 'return_pending', 'overdue'], true);
    }

    /**
     * Sentence people can scan at a glance, e.g. "Joe has 2 items · Pinewood".
     */
    public function getSummaryAttribute(): string
    {
        $who = 'Someone';
        if ($this->relationLoaded('borrower') && $this->borrower?->name) {
            $who = explode(' ', trim($this->borrower->name))[0];
        }

        $verb = match ($this->status) {
            'reserved' => 'is picking up',
            'returned', 'closed' => 'returned',
            default => 'has',
        };

        $where = $this->relationLoaded('property') ? $this->property?->name : null;
        $sentence = trim("{$who} {$verb} {$this->items_label}");

        return $where ? "{$sentence} for {$where}" : $sentence;
    }

    public function getItemsLabelAttribute(): string
    {
        if (! $this->relationLoaded('items')) {
            return 'equipment';
        }

        $names = $this->items
            ->map(fn (LoanItem $loanItem) => $loanItem->relationLoaded('item') ? $loanItem->item?->displayName() : null)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            $count = $this->items->count();

            return $count === 1 ? '1 item' : "{$count} items";
        }

        $extra = $names->count() - 1;

        return $extra > 0
            ? $names->first().' + '.$extra.' more '.($extra === 1 ? 'item' : 'items')
            : $names->first();
    }
}
