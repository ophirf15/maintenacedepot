<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowRequestLine extends Model
{
    protected $appends = ['label'];

    protected $fillable = [
        'borrow_request_id', 'line_no', 'request_mode', 'item_id', 'tool_type_id',
        'quantity', 'allocated_item_id', 'status', 'notes', 'reject_reason',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class, 'borrow_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    public function allocatedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'allocated_item_id');
    }

    /**
     * Plain-language name for the line, e.g. "Pressure Washer (any unit)".
     */
    public function getLabelAttribute(): string
    {
        if ($this->relationLoaded('item') && $this->item) {
            return $this->item->displayName();
        }

        if ($this->relationLoaded('toolType') && $this->toolType) {
            return $this->toolType->name.' (any unit)';
        }

        if ($this->relationLoaded('allocatedItem') && $this->allocatedItem) {
            return $this->allocatedItem->displayName();
        }

        return 'Item '.$this->line_no;
    }
}
