<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BorrowRequest extends Model
{
    use SoftDeletes;

    protected $appends = ['summary', 'items_label'];

    protected $fillable = [
        'reference', 'property_id', 'requester_id', 'on_behalf_of_id', 'pickup_depot_id',
        'status', 'priority', 'purpose', 'needed_from', 'needed_until', 'expected_dropoff_at',
        'submitted_at', 'approved_at', 'approved_by', 'approval_note', 'rejected_at',
        'rejected_by', 'rejection_reason', 'modification_requested_at', 'modification_requested_by',
        'modification_note', 'modification_snapshot', 'modification_accepted', 'reserved_at',
        'pickup_deadline_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_from' => 'datetime',
            'needed_until' => 'datetime',
            'expected_dropoff_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'modification_requested_at' => 'datetime',
            'modification_snapshot' => 'array',
            'modification_accepted' => 'boolean',
            'reserved_at' => 'datetime',
            'pickup_deadline_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_id');
    }

    public function pickupDepot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'pickup_depot_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BorrowRequestLine::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    /**
     * Sentence people can scan at a glance, e.g.
     * "Mike requested 2 pressure washers for Pinewood".
     */
    public function getSummaryAttribute(): string
    {
        $who = $this->borrowerName();
        $what = $this->items_label;
        $where = $this->relationLoaded('property') ? $this->property?->name : null;

        $sentence = trim("{$who} requested {$what}");

        return $where ? "{$sentence} for {$where}" : $sentence;
    }

    public function getItemsLabelAttribute(): string
    {
        if (! $this->relationLoaded('lines')) {
            return 'equipment';
        }

        $labels = $this->lines
            ->map(fn (BorrowRequestLine $line) => [
                'label' => $line->label,
                'qty' => max(1, (int) ceil((float) $line->quantity)),
            ])
            ->groupBy('label');

        if ($labels->isEmpty()) {
            return 'equipment';
        }

        $first = $labels->first();
        $firstLabel = $first->first()['label'];
        $firstQty = $first->sum('qty');
        $head = $firstQty > 1 ? "{$firstQty} × {$firstLabel}" : $firstLabel;

        $extra = $labels->count() - 1;

        return $extra > 0
            ? $head.' + '.$extra.' more '.($extra === 1 ? 'item' : 'items')
            : $head;
    }

    protected function borrowerName(): string
    {
        $user = $this->relationLoaded('onBehalfOf') ? $this->onBehalfOf : null;
        $user ??= $this->relationLoaded('requester') ? $this->requester : null;

        if (! $user?->name) {
            return 'Someone';
        }

        return explode(' ', trim($user->name))[0];
    }
}
