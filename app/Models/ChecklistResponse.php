<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistResponse extends Model
{
    protected $fillable = [
        'checklist_template_id', 'context', 'item_id', 'loan_id',
        'completed_by', 'completed_at', 'result', 'answers',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'answers' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
}
