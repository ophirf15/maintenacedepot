<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCart extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
        'pickup_depot_id',
        'needed_from',
        'needed_until',
        'purpose',
        'priority',
        'lines',
    ];

    protected function casts(): array
    {
        return [
            'needed_from' => 'datetime',
            'needed_until' => 'datetime',
            'lines' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function pickupDepot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'pickup_depot_id');
    }

    /** Empty cart payload for users who have never saved one. */
    public static function emptyPayload(): array
    {
        return [
            'property_id' => null,
            'pickup_depot_id' => null,
            'needed_from' => null,
            'needed_until' => null,
            'purpose' => null,
            'priority' => 'normal',
            'lines' => [],
        ];
    }

    public function toApiArray(): array
    {
        return [
            'property_id' => $this->property_id,
            'pickup_depot_id' => $this->pickup_depot_id,
            'needed_from' => $this->needed_from?->toIso8601String(),
            'needed_until' => $this->needed_until?->toIso8601String(),
            'purpose' => $this->purpose,
            'priority' => $this->priority ?: 'normal',
            'lines' => array_values($this->lines ?? []),
        ];
    }
}
