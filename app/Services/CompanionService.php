<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemLink;
use App\Models\Loan;
use App\Models\ToolTypeLink;
use Illuminate\Support\Collection;

class CompanionService
{
    /**
     * Suggestions for each primary (non-companion) line on a loan.
     *
     * @return list<array{
     *   loan_item_id: int,
     *   item_id: int,
     *   item_label: string,
     *   companions: list<array<string, mixed>>,
     *   consumables: list<array<string, mixed>>,
     *   required_skipped_hints: list<string>
     * }>
     */
    public function suggestionsForLoan(Loan $loan): array
    {
        $loan->loadMissing(['items.item.toolType', 'items.item.status']);

        $onLoanIds = $loan->items->pluck('item_id')->all();
        $out = [];

        foreach ($loan->items as $loanItem) {
            if ($loanItem->companion_of_loan_item_id) {
                continue;
            }

            $item = $loanItem->item;
            if (! $item || $item->is_consumable) {
                continue;
            }

            $companions = $this->availableCompanionsFor($item, $onLoanIds);
            $consumables = $this->consumableSkusFor($item);

            $out[] = [
                'loan_item_id' => $loanItem->id,
                'item_id' => $item->id,
                'item_label' => $item->displayName(),
                'companions' => $companions->values()->all(),
                'consumables' => $consumables->values()->all(),
                'required_skipped_hints' => $this->requiredHints($item, $companions),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $excludeItemIds
     * @return Collection<int, array<string, mixed>>
     */
    public function availableCompanionsFor(Item $item, array $excludeItemIds = []): Collection
    {
        $byId = collect();

        $linkedIds = ItemLink::query()
            ->where('parent_item_id', $item->id)
            ->where('role', 'companion')
            ->pluck('child_item_id', 'id');

        $links = ItemLink::query()
            ->where('parent_item_id', $item->id)
            ->where('role', 'companion')
            ->get()
            ->keyBy('child_item_id');

        if ($linkedIds->isNotEmpty()) {
            Item::query()
                ->with(['status', 'toolType'])
                ->whereIn('id', $linkedIds->values())
                ->where('is_consumable', false)
                ->get()
                ->each(function (Item $candidate) use ($byId, $excludeItemIds, $links) {
                    if (in_array($candidate->id, $excludeItemIds, true)) {
                        return;
                    }
                    if (! $candidate->isAvailableForBorrow()) {
                        return;
                    }
                    $link = $links->get($candidate->id);
                    $byId->put($candidate->id, $this->companionPayload($candidate, (bool) ($link?->is_required), 'item_link'));
                });
        }

        if ($item->tool_type_id) {
            $typeLinks = ToolTypeLink::query()
                ->where('parent_tool_type_id', $item->tool_type_id)
                ->where('role', 'companion')
                ->get();

            foreach ($typeLinks as $typeLink) {
                Item::query()
                    ->with(['status', 'toolType'])
                    ->where('tool_type_id', $typeLink->child_tool_type_id)
                    ->where('is_consumable', false)
                    ->where('is_loanable', true)
                    ->whereNotIn('id', $excludeItemIds)
                    ->limit(40)
                    ->get()
                    ->each(function (Item $candidate) use ($byId, $typeLink) {
                        if (! $candidate->isAvailableForBorrow()) {
                            return;
                        }
                        if ($byId->has($candidate->id)) {
                            if ($typeLink->is_required) {
                                $row = $byId->get($candidate->id);
                                $row['is_required'] = true;
                                $byId->put($candidate->id, $row);
                            }

                            return;
                        }
                        $byId->put(
                            $candidate->id,
                            $this->companionPayload($candidate, (bool) $typeLink->is_required, 'tool_type_link')
                        );
                    });
            }
        }

        return $byId->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function consumableSkusFor(Item $item): Collection
    {
        $rows = collect();

        $linked = ItemLink::query()
            ->where('parent_item_id', $item->id)
            ->where('role', 'consumable')
            ->get();

        if ($linked->isNotEmpty()) {
            Item::query()
                ->whereIn('id', $linked->pluck('child_item_id'))
                ->where('is_consumable', true)
                ->get()
                ->each(function (Item $sku) use ($rows, $linked) {
                    $link = $linked->firstWhere('child_item_id', $sku->id);
                    $rows->put($sku->id, $this->consumablePayload($sku, (bool) ($link?->is_required), 'item_link'));
                });
        }

        if ($item->tool_type_id) {
            $typeLinks = ToolTypeLink::query()
                ->where('parent_tool_type_id', $item->tool_type_id)
                ->where('role', 'consumable')
                ->get();

            foreach ($typeLinks as $typeLink) {
                Item::query()
                    ->where('tool_type_id', $typeLink->child_tool_type_id)
                    ->where('is_consumable', true)
                    ->limit(40)
                    ->get()
                    ->each(function (Item $sku) use ($rows, $typeLink) {
                        if ($rows->has($sku->id)) {
                            return;
                        }
                        $rows->put($sku->id, $this->consumablePayload($sku, (bool) $typeLink->is_required, 'tool_type_link'));
                    });
            }
        }

        return $rows->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $availableCompanions
     * @return list<string>
     */
    protected function requiredHints(Item $item, Collection $availableCompanions): array
    {
        $hints = [];

        $requiredItemLinks = ItemLink::query()
            ->where('parent_item_id', $item->id)
            ->where('role', 'companion')
            ->where('is_required', true)
            ->exists();

        $requiredTypeLinks = $item->tool_type_id
            ? ToolTypeLink::query()
                ->where('parent_tool_type_id', $item->tool_type_id)
                ->where('role', 'companion')
                ->where('is_required', true)
                ->exists()
            : false;

        if (($requiredItemLinks || $requiredTypeLinks) && $availableCompanions->isNotEmpty()) {
            $hints[] = $item->displayName().' usually goes out with a companion (e.g. battery or mask).';
        }

        return $hints;
    }

    /**
     * @return array<string, mixed>
     */
    protected function companionPayload(Item $item, bool $required, string $source): array
    {
        return [
            'id' => $item->id,
            'label' => $item->displayName(),
            'asset_tag' => $item->asset_tag,
            'numeric_code' => $item->numeric_code,
            'tool_type_id' => $item->tool_type_id,
            'tool_type' => $item->toolType?->name,
            'is_required' => $required,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function consumablePayload(Item $sku, bool $required, string $source): array
    {
        return [
            'id' => $sku->id,
            'label' => $sku->displayName(),
            'stock_qty' => (float) $sku->stock_qty,
            'stock_unit' => $sku->stock_unit ?: 'ea',
            'reorder_point' => (float) $sku->reorder_point,
            'is_required' => $required,
            'source' => $source,
            'supplier_name' => $sku->supplier_name,
            'supplier_part_number' => $sku->supplier_part_number,
            'typical_cost' => $sku->typical_cost !== null ? (float) $sku->typical_cost : null,
        ];
    }
}
