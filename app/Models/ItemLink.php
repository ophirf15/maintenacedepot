<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLink extends Model
{
    protected $fillable = ['parent_item_id', 'child_item_id', 'is_required', 'role'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'child_item_id');
    }
}
