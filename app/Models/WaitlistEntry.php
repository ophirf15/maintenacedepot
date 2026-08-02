<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'property_id', 'user_id', 'borrow_request_id', 'borrow_request_line_id',
        'item_id', 'tool_type_id', 'position', 'desired_from', 'desired_until', 'status',
    ];

    protected function casts(): array
    {
        return [
            'desired_from' => 'datetime',
            'desired_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
