<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'version', 'previous_version', 'applied_at', 'applied_by',
        'status', 'release_notes', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }
}
