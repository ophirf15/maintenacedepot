<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'scope_type', 'scope_id', 'notification_type_id', 'channel', 'is_enabled', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }
}
