<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCart;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request)
    {
        $cart = UserCart::query()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'data' => $cart ? $cart->toApiArray() : UserCart::emptyPayload(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatedPayload($request);

        $cart = UserCart::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'property_id' => $data['property_id'] ?? null,
                'pickup_depot_id' => $data['pickup_depot_id'] ?? null,
                'needed_from' => $data['needed_from'] ?? null,
                'needed_until' => $data['needed_until'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'lines' => $data['lines'] ?? [],
            ]
        );

        return response()->json(['data' => $cart->fresh()->toApiArray()]);
    }

    public function destroy(Request $request)
    {
        $cart = UserCart::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            UserCart::emptyPayload()
        );

        $cart->update(UserCart::emptyPayload());

        return response()->json(['data' => $cart->fresh()->toApiArray()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'pickup_depot_id' => 'nullable|exists:depots,id',
            'needed_from' => 'nullable|date',
            'needed_until' => 'nullable|date',
            'purpose' => 'nullable|string|max:5000',
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'lines' => 'nullable|array',
            'lines.*.request_mode' => 'required|in:specific_item,tool_type',
            'lines.*.item_id' => 'nullable|exists:items,id',
            'lines.*.tool_type_id' => 'nullable|exists:tool_types,id',
            'lines.*.quantity' => 'nullable|numeric|min:1',
            'lines.*.notes' => 'nullable|string|max:255',
            'lines.*.label' => 'nullable|string|max:255',
            'lines.*.icon' => 'nullable|string|max:64',
            'lines.*._key' => 'nullable|string|max:64',
            'lines.*.image_url' => 'nullable|string|max:2048',
            'lines.*.specs' => 'nullable|array',
        ]);

        $from = $data['needed_from'] ?? null;
        $until = $data['needed_until'] ?? null;

        if ($from && $until && strtotime((string) $until) <= strtotime((string) $from)) {
            throw ValidationException::withMessages([
                'needed_until' => 'The return date must be after the pick-up date.',
            ]);
        }

        foreach ($data['lines'] ?? [] as $index => $line) {
            if (($line['request_mode'] ?? null) === 'tool_type' && empty($line['tool_type_id'])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tool_type_id" => 'A tool type is required for this line.',
                ]);
            }

            if (($line['request_mode'] ?? null) === 'specific_item' && empty($line['item_id'])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.item_id" => 'An item is required for this line.',
                ]);
            }
        }

        $data['lines'] = array_map(function (array $line) {
            $quantity = max(1, (float) ($line['quantity'] ?? 1));
            $key = $line['_key'] ?? (
                ! empty($line['item_id'])
                    ? 'item-'.$line['item_id']
                    : 'type-'.($line['tool_type_id'] ?? 'unknown')
            );

            return array_filter([
                'request_mode' => $line['request_mode'],
                'item_id' => $line['item_id'] ?? null,
                'tool_type_id' => $line['tool_type_id'] ?? null,
                'quantity' => $quantity,
                'notes' => $line['notes'] ?? '',
                'label' => $line['label'] ?? null,
                'icon' => $line['icon'] ?? null,
                '_key' => $key,
                'image_url' => $line['image_url'] ?? null,
                'specs' => $line['specs'] ?? null,
            ], fn ($value) => $value !== null);
        }, $data['lines'] ?? []);

        return $data;
    }
}
