<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
    ): AuditEvent {
        $user = Auth::user();

        return AuditEvent::query()->create([
            'user_id' => $user?->id,
            'actor_label' => $user?->name,
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
