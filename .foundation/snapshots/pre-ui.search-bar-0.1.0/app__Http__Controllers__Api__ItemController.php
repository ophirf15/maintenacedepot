<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemLink;
use App\Models\ItemSpecValue;
use App\Models\ToolTypeSpecField;
use App\Services\AuditLogger;
use App\Services\ReferenceGenerator;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function __construct(private AuditLogger $audit, private ReferenceGenerator $refs) {}

    public function index(Request $request)
    {
        $query = Item::query()->with(['toolType.category', 'toolType.specFields', 'status', 'depot', 'specValues.field']);

        if ($categoryId = $request->integer('category')) {
            $query->whereHas('toolType', fn ($q) => $q->where('category_id', $categoryId));
        }

        if ($toolTypeId = $request->integer('tool_type')) {
            $query->where('tool_type_id', $toolTypeId);
        }

        if ($depotId = $request->integer('depot')) {
            $query->where('depot_id', $depotId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $status));
        }

        if ($request->has('is_consumable')) {
            $query->where('is_consumable', $request->boolean('is_consumable'));
        }

        if ($request->boolean('low_stock')) {
            $query->where('is_consumable', true)->whereColumn('stock_qty', '<=', 'reorder_point');
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('numeric_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('supplier_part_number', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function show(Item $item)
    {
        $item->load([
            'toolType.category', 'toolType.specFields', 'status', 'depot', 'homeDepot', 'currentProperty',
            'linkedChildren', 'linkedParents', 'maintenancePlans.maintenanceType',
            'specValues.field', 'attachments',
            'tickets' => fn ($q) => $q->latest()->limit(20),
        ]);

        $item->tickets->load('attachments');

        $damagePhotos = $item->attachments
            ->where('collection', 'damage')
            ->values()
            ->concat(
                $item->tickets->flatMap(fn ($ticket) => $ticket->attachments->where('collection', 'damage'))
            )
            ->map(fn ($att) => [
                'id' => $att->id,
                'url' => PublicStorageUrl::path($att->path),
                'original_name' => $att->original_name,
                'ticket_id' => $att->attachable_type === \App\Models\Ticket::class ? $att->attachable_id : null,
                'created_at' => $att->created_at,
            ])
            ->values();

        return response()->json([
            'data' => $item,
            'damage_photos' => $damagePhotos,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $specs = $data['specs'] ?? [];
        unset($data['specs']);

        if (! empty($data['qr_token'])) {
            $data['qr_token'] = strtolower(trim($data['qr_token']));
        } else {
            $data['qr_token'] = $this->refs->qrToken();
        }

        $data['asset_tag'] = $data['asset_tag'] ?? $this->refs->assetTag();
        if (empty($data['numeric_code'])) {
            $data['numeric_code'] = $this->refs->numericCode();
        }
        $data['created_by'] = $request->user()?->id;

        $item = Item::query()->create($data);
        $this->syncSpecs($item, $specs);

        $this->audit->log('created', $item, null, $item->toArray());

        return response()->json([
            'data' => $item->fresh(['toolType', 'status', 'depot', 'specValues.field']),
        ], 201);
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validated($request, $item->id);
        $specs = $data['specs'] ?? null;
        unset($data['specs']);

        if (array_key_exists('qr_token', $data)) {
            if ($data['qr_token']) {
                $data['qr_token'] = strtolower(trim($data['qr_token']));
            } else {
                unset($data['qr_token']);
            }
        }

        $old = $item->toArray();
        $item->update($data);

        if (is_array($specs)) {
            $this->syncSpecs($item, $specs);
        }

        $this->audit->log('updated', $item, $old, $item->toArray());

        return response()->json([
            'data' => $item->fresh(['toolType', 'status', 'depot', 'specValues.field']),
        ]);
    }

    public function destroy(Item $item)
    {
        $old = $item->toArray();
        $item->delete();

        $this->audit->log('deleted', $item, $old, null);

        return response()->json(['ok' => true]);
    }

    public function uploadManual(Request $request, Item $item)
    {
        $request->validate([
            'manual' => 'required|file|mimes:pdf,doc,docx|max:20480',
        ]);

        $path = $request->file('manual')->store('manuals', 'public');

        $old = $item->toArray();
        $item->update(['manual_path' => $path]);

        $this->audit->log('manual_uploaded', $item, $old, $item->toArray());

        return response()->json([
            'data' => $item->fresh(['toolType.category', 'status', 'depot']),
            'url' => PublicStorageUrl::path($path),
        ]);
    }

    public function uploadImage(Request $request, Item $item)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $path = $request->file('image')->store('items', 'public');

        $old = $item->toArray();
        $item->update(['image_path' => $path]);

        $this->audit->log('image_uploaded', $item, $old, $item->toArray());

        return response()->json([
            'data' => $item->fresh(['toolType.category', 'status', 'depot', 'specValues.field']),
            'url' => PublicStorageUrl::path($path),
        ]);
    }

    public function linkItems(Request $request, Item $item)
    {
        $data = $request->validate([
            'child_item_ids' => 'required|array|min:1',
            'child_item_ids.*' => 'exists:items,id',
            'is_required' => 'boolean',
            'role' => 'nullable|in:companion,consumable',
        ]);

        $role = $data['role'] ?? 'companion';
        $item->update(['is_kit' => true]);

        foreach ($data['child_item_ids'] as $childId) {
            if ((int) $childId === $item->id) {
                continue;
            }

            ItemLink::query()->updateOrCreate(
                ['parent_item_id' => $item->id, 'child_item_id' => $childId],
                [
                    'is_required' => $data['is_required'] ?? false,
                    'role' => $role,
                ]
            );
        }

        $this->audit->log('items_linked', $item, null, [
            'children' => $data['child_item_ids'],
            'role' => $role,
        ]);

        return response()->json([
            'data' => $item->fresh(['linkedChildren', 'linkedParents']),
        ]);
    }

    public function unlinkItem(Item $item, Item $child)
    {
        ItemLink::query()
            ->where('parent_item_id', $item->id)
            ->where('child_item_id', $child->id)
            ->delete();

        if (! ItemLink::query()->where('parent_item_id', $item->id)->exists()) {
            $item->update(['is_kit' => false]);
        }

        $this->audit->log('items_unlinked', $item, null, ['child_id' => $child->id]);

        return response()->json([
            'data' => $item->fresh(['linkedChildren', 'linkedParents']),
        ]);
    }

    /**
     * @param  array<string, string>  $specs  field key => value
     */
    protected function syncSpecs(Item $item, array $specs): void
    {
        $fields = ToolTypeSpecField::query()
            ->where('tool_type_id', $item->tool_type_id)
            ->get()
            ->keyBy('key');

        foreach ($specs as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            $value = is_string($value) ? trim($value) : (string) $value;

            if ($value === '') {
                ItemSpecValue::query()
                    ->where('item_id', $item->id)
                    ->where('tool_type_spec_field_id', $field->id)
                    ->delete();

                continue;
            }

            ItemSpecValue::query()->updateOrCreate(
                ['item_id' => $item->id, 'tool_type_spec_field_id' => $field->id],
                ['value' => $value]
            );
        }
    }

    protected function validated(Request $request, ?int $itemId = null): array
    {
        $ignore = $itemId ? ",{$itemId}" : '';

        return $request->validate([
            'depot_id' => 'required|exists:depots,id',
            'home_depot_id' => 'nullable|exists:depots,id',
            'tool_type_id' => 'required|exists:tool_types,id',
            'custom_status_id' => 'required|exists:custom_statuses,id',
            'parent_item_id' => 'nullable|exists:items,id',
            'current_property_id' => 'nullable|exists:properties,id',
            'is_kit' => 'boolean',
            'asset_tag' => "sometimes|string|max:64|unique:items,asset_tag{$ignore}",
            'numeric_code' => [
                'nullable',
                'digits:6',
                Rule::unique('items', 'numeric_code')->ignore($itemId),
            ],
            'qr_token' => [
                'nullable',
                'string',
                'min:8',
                'max:64',
                Rule::unique('items', 'qr_token')->ignore($itemId),
            ],
            'serial_number' => 'nullable|string|max:120',
            'name' => 'nullable|string|max:190',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string|max:255',
            'condition' => 'in:new,good,fair,poor',
            'is_consumable' => 'boolean',
            'stock_qty' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'reorder_qty' => 'nullable|numeric|min:0',
            'stock_unit' => 'nullable|string|max:24',
            'supplier_name' => 'nullable|string|max:160',
            'supplier_part_number' => 'nullable|string|max:120',
            'typical_cost' => 'nullable|numeric|min:0',
            'is_loanable' => 'boolean',
            'usage_hours' => 'nullable|numeric|min:0',
            'fuel_pct' => 'nullable|integer|min:0|max:100',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'warranty_expires_on' => 'nullable|date',
            'lifespan_years' => 'nullable|integer|min:1|max:100',
            'salvage_value' => 'nullable|numeric|min:0',
            'replacement_cost' => 'nullable|numeric|min:0',
            'end_of_life_soon' => 'boolean',
            'location_note' => 'nullable|string|max:190',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string',
            'specs' => 'nullable|array',
            'specs.*' => 'nullable|string|max:255',
        ]);
    }
}
