<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id', 'tool_type_id', 'maintenance_type_id', 'name', 'trigger_type',
        'interval_days', 'interval_hours', 'interval_loans', 'interval_fuel_cycles',
        'next_due_at', 'next_due_hours', 'next_due_loans', 'next_due_fuel_cycles',
        'last_performed_at', 'blocks_checkout_when_overdue', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_hours' => 'decimal:2',
            'next_due_at' => 'datetime',
            'next_due_hours' => 'decimal:2',
            'last_performed_at' => 'datetime',
            'blocks_checkout_when_overdue' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function isOverdue(): bool
    {
        return app(\App\Services\MaintenancePlanService::class)->isOverdue($this, $this->item);
    }
}
