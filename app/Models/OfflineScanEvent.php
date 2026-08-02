<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineScanEvent extends Model
{
    protected $fillable = [
        'user_id', 'client_uuid', 'action', 'qr_token', 'loan_id',
        'payload', 'scanned_at', 'synced_at', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
