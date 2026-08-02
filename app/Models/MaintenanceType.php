<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'kind', 'description', 'requires_downtime', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_downtime' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
