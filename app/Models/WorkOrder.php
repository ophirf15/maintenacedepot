<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'ticket_id', 'item_id', 'maintenance_type_id', 'maintenance_plan_id',
        'title', 'description', 'status', 'priority', 'is_recurring', 'assigned_to',
        'scheduled_start_at', 'completed_at', 'completed_by', 'labour_hours', 'parts_cost',
        'total_cost', 'completion_notes', 'parts_used',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'scheduled_start_at' => 'datetime',
            'completed_at' => 'datetime',
            'labour_hours' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }
}
