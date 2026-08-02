<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'item_id', 'loan_id', 'ticket_type', 'maintenance_type_id',
        'title', 'description', 'severity', 'priority', 'status', 'takes_out_of_service',
        'reported_by', 'assigned_to', 'resolved_at', 'resolved_by', 'resolution_code',
        'resolution_notes', 'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'takes_out_of_service' => 'boolean',
            'resolved_at' => 'datetime',
            'total_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
