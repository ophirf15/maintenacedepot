<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ToolType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartCheckoutService
{
    public function __construct(private BorrowService $borrow) {}

    /**
     * Derive pickup depots from tools, group lines, and create one request per depot.
     *
     * @param  array<string, mixed>  $data
     * @return array{requests: list<\App\Models\BorrowRequest>, split: bool}
     */
    public function checkout(User $user, array $data): array
    {
        $resolved = $this->resolveLinesByDepot($data['lines'] ?? []);

        if ($resolved->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one tool before submitting.',
            ]);
        }

        $shouldSubmit = (bool) ($data['submit'] ?? false);
        $requests = [];

        foreach ($resolved as $depotId => $lines) {
            $payload = [
                'property_id' => $data['property_id'],
                'on_behalf_of_id' => $data['on_behalf_of_id'] ?? null,
                'pickup_depot_id' => (int) $depotId,
                'priority' => $data['priority'] ?? 'normal',
                'purpose' => $data['purpose'] ?? null,
                'needed_from' => $data['needed_from'],
                'needed_until' => $data['needed_until'],
                'expected_dropoff_at' => $data['expected_dropoff_at'] ?? null,
                'lines' => $lines,
            ];

            $request = $this->borrow->createDraft($user, $payload);

            if ($shouldSubmit) {
                $request = $this->borrow->submit($request);
            }

            $requests[] = $request->load(BorrowService::DETAIL_RELATIONS);
        }

        return [
            'requests' => $requests,
            'split' => count($requests) > 1,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, list<array<string, mixed>>> keyed by depot_id
     */
    public function resolveLinesByDepot(array $lines): Collection
    {
        $grouped = [];

        foreach ($lines as $index => $line) {
            $depotId = $this->resolveDepotId($line, $index);

            $grouped[$depotId] ??= [];
            $grouped[$depotId][] = $line;
        }

        // Stable order by depot id for deterministic responses.
        ksort($grouped);

        return collect($grouped);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function resolveDepotId(array $line, int $index): int
    {
        $mode = $line['request_mode'] ?? null;

        if ($mode === 'specific_item') {
            $itemId = $line['item_id'] ?? null;
            if (! $itemId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.item_id" => 'An item is required for this line.',
                ]);
            }

            $item = Item::query()->find($itemId);
            if (! $item || ! $item->depot_id) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'That unit has no depot location on file.',
                ]);
            }

            return (int) $item->depot_id;
        }

        if ($mode === 'tool_type') {
            $toolTypeId = $line['tool_type_id'] ?? null;
            if (! $toolTypeId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tool_type_id" => 'A tool type is required for this line.',
                ]);
            }

            $depotId = $this->bestDepotForToolType((int) $toolTypeId);
            if (! $depotId) {
                $name = ToolType::query()->where('id', $toolTypeId)->value('name') ?: 'that tool';

                throw ValidationException::withMessages([
                    'lines' => "No free units of {$name} are available at any depot right now.",
                ]);
            }

            return $depotId;
        }

        throw ValidationException::withMessages([
            "lines.{$index}.request_mode" => 'Unknown request mode.',
        ]);
    }

    /**
     * Depot with the most available loanable stock; ties break to lowest depot id.
     */
    public function bestDepotForToolType(int $toolTypeId): ?int
    {
        $units = $this->borrow->availableUnits($toolTypeId);

        if ($units->isEmpty()) {
            return null;
        }

        $counts = $units
            ->groupBy('depot_id')
            ->map(fn (Collection $group, $depotId) => [
                'depot_id' => (int) $depotId,
                'count' => $group->count(),
            ])
            ->sort(function (array $a, array $b) {
                if ($a['count'] === $b['count']) {
                    return $a['depot_id'] <=> $b['depot_id'];
                }

                return $b['count'] <=> $a['count'];
            })
            ->values();

        return $counts->first()['depot_id'] ?? null;
    }
}
