<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ToolType;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ToolTypeController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = ToolType::query()->with(['category', 'specFields'])->withCount('items');

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:160',
            'sku_prefix' => 'nullable|string|max:16',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:64',
            'image_path' => 'nullable|string|max:255',
            'is_consumable' => 'boolean',
            'manufacturer' => 'nullable|string|max:120',
            'model' => 'nullable|string|max:120',
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'max_loan_days' => 'nullable|integer|min:1|max:365',
            'tracks_fuel' => 'boolean',
            'fuel_type' => 'nullable|string|max:16',
            'tracks_usage_hours' => 'boolean',
            'allow_waitlist' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);

        $toolType = ToolType::query()->create($data);

        $this->audit->log('created', $toolType, null, $toolType->toArray());

        return response()->json(['data' => $toolType->load(['category', 'specFields'])], 201);
    }

    public function update(Request $request, ToolType $toolType)
    {
        $data = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string|max:160',
            'sku_prefix' => 'nullable|string|max:16',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:64',
            'image_path' => 'nullable|string|max:255',
            'is_consumable' => 'boolean',
            'manufacturer' => 'nullable|string|max:120',
            'model' => 'nullable|string|max:120',
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'max_loan_days' => 'nullable|integer|min:1|max:365',
            'tracks_fuel' => 'boolean',
            'fuel_type' => 'nullable|string|max:16',
            'tracks_usage_hours' => 'boolean',
            'allow_waitlist' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $old = $toolType->toArray();
        $toolType->update($data);

        $this->audit->log('updated', $toolType, $old, $toolType->toArray());

        return response()->json(['data' => $toolType->fresh(['category', 'specFields'])]);
    }

    public function links(ToolType $toolType)
    {
        $links = $toolType->companionLinks()->with('childType')->orderBy('id')->get();

        return response()->json(['data' => $links]);
    }

    public function syncLinks(Request $request, ToolType $toolType)
    {
        $data = $request->validate([
            'links' => 'present|array',
            'links.*.child_tool_type_id' => 'required|exists:tool_types,id',
            'links.*.role' => 'required|in:companion,consumable',
            'links.*.is_required' => 'boolean',
        ]);

        $keep = [];
        foreach ($data['links'] as $row) {
            if ((int) $row['child_tool_type_id'] === $toolType->id) {
                continue;
            }
            $link = \App\Models\ToolTypeLink::query()->updateOrCreate(
                [
                    'parent_tool_type_id' => $toolType->id,
                    'child_tool_type_id' => $row['child_tool_type_id'],
                    'role' => $row['role'],
                ],
                ['is_required' => $row['is_required'] ?? false]
            );
            $keep[] = $link->id;
        }

        \App\Models\ToolTypeLink::query()
            ->where('parent_tool_type_id', $toolType->id)
            ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
            ->when($keep === [], fn ($q) => $q)
            ->delete();

        $this->audit->log('tool_type_links_synced', $toolType, null, ['links' => $data['links']]);

        return response()->json([
            'data' => $toolType->companionLinks()->with('childType')->orderBy('id')->get(),
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (ToolType::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
