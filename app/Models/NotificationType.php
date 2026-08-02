<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'group', 'default_channels', 'available_channels', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'available_channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(NotificationSetting::class);
    }
}
