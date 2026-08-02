<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomStatus extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'availability_effect', 'color', 'icon', 'description',
        'is_default', 'is_system', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->availability_effect === 'available';
    }
}
