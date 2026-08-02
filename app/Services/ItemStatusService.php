<?php

namespace App\Services;

use App\Models\CustomStatus;
use App\Models\Item;

/**
 * Single place that flips equipment in and out of circulation, so a tool flagged
 * unsafe anywhere in the app really does stop appearing as borrowable.
 */
class ItemStatusService
{
    public function __construct(private AuditLogger $audit) {}

    public function takeOutOfService(Item $item, ?string $reason = null): Item
    {
        return $this->moveTo($item, 'out-of-service', $reason ?? 'Taken out of service');
    }

    public function sendToMaintenance(Item $item, ?string $reason = null): Item
    {
        return $this->moveTo($item, 'in-maintenance', $reason ?? 'Sent for servicing');
    }

    public function restoreToService(Item $item, ?string $reason = null): Item
    {
        return $this->moveTo($item, 'available', $reason ?? 'Back in service');
    }

    public function statusId(string $slug): ?int
    {
        return CustomStatus::query()->where('slug', $slug)->value('id');
    }

    private function moveTo(Item $item, string $slug, string $reason): Item
    {
        $statusId = $this->statusId($slug);

        if (! $statusId || (int) $item->custom_status_id === (int) $statusId) {
            return $item;
        }

        $old = $item->toArray();
        $item->update(['custom_status_id' => $statusId]);

        $this->audit->log('status_changed', $item, $old, $item->toArray(), $reason);

        return $item->fresh(['status']);
    }
}
