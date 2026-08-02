<?php

namespace App\Services;

use App\Models\Item;
use App\Models\LoanConsumableIssue;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function restock(Item $item, float $qty, User $actor, ?string $notes = null): Item
    {
        if (! $item->is_consumable) {
            throw ValidationException::withMessages(['item' => 'Only consumable SKUs can be restocked.']);
        }
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Restock quantity must be greater than zero.']);
        }

        return $this->applyDelta($item, $qty, 'restock', $actor, $notes);
    }

    /**
     * Set absolute on-hand qty (adjustment).
     */
    public function adjustTo(Item $item, float $absoluteQty, User $actor, ?string $notes = null): Item
    {
        if (! $item->is_consumable) {
            throw ValidationException::withMessages(['item' => 'Only consumable SKUs can be adjusted.']);
        }
        if ($absoluteQty < 0) {
            throw ValidationException::withMessages(['qty' => 'Stock cannot be negative.']);
        }

        $delta = $absoluteQty - (float) $item->stock_qty;

        return $this->applyDelta($item, $delta, 'adjustment', $actor, $notes);
    }

    public function confirmIssue(
        LoanConsumableIssue $issue,
        float $qtyUsed,
        User $actor,
        bool $allowNegative = false,
        ?string $notes = null,
    ): LoanConsumableIssue {
        if ($issue->status === 'confirmed') {
            return $issue;
        }
        if ($qtyUsed < 0) {
            throw ValidationException::withMessages(['qty_used' => 'Used quantity cannot be negative.']);
        }

        return DB::transaction(function () use ($issue, $qtyUsed, $actor, $allowNegative, $notes) {
            $item = Item::query()->lockForUpdate()->findOrFail($issue->item_id);
            if (! $item->is_consumable) {
                throw ValidationException::withMessages(['item' => 'Issue item is not a consumable.']);
            }

            $balance = (float) $item->stock_qty;
            if ($qtyUsed > $balance && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'qty_used' => "Only {$balance} {$item->stock_unit} on hand. Lower the qty or tick override.",
                ]);
            }

            $newBalance = $balance - $qtyUsed;
            $item->update(['stock_qty' => $newBalance]);

            StockMovement::query()->create([
                'item_id' => $item->id,
                'delta' => -$qtyUsed,
                'balance_after' => $newBalance,
                'reason' => 'issue_confirm',
                'loan_id' => $issue->loan_id,
                'loan_consumable_issue_id' => $issue->id,
                'user_id' => $actor->id,
                'notes' => $notes,
            ]);

            $issue->update([
                'qty_used' => $qtyUsed,
                'status' => 'confirmed',
                'notes' => $notes ?? $issue->notes,
            ]);

            return $issue->fresh(['item']);
        });
    }

    public function lowStockQuery(): Builder
    {
        return Item::query()
            ->where('is_consumable', true)
            ->whereColumn('stock_qty', '<=', 'reorder_point')
            ->with(['toolType', 'depot']);
    }

    protected function applyDelta(
        Item $item,
        float $delta,
        string $reason,
        User $actor,
        ?string $notes = null,
    ): Item {
        return DB::transaction(function () use ($item, $delta, $reason, $actor, $notes) {
            $locked = Item::query()->lockForUpdate()->findOrFail($item->id);
            $newBalance = (float) $locked->stock_qty + $delta;
            if ($newBalance < 0) {
                throw ValidationException::withMessages(['qty' => 'Stock cannot go below zero.']);
            }

            $locked->update(['stock_qty' => $newBalance]);

            StockMovement::query()->create([
                'item_id' => $locked->id,
                'delta' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'user_id' => $actor->id,
                'notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }
}
