<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomStatus;
use App\Models\Item;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(Request $request)
    {
        $availableStatusIds = CustomStatus::query()
            ->where('availability_effect', 'available')
            ->pluck('id');

        $depotId = $request->integer('depot_id');

        $categories = Category::query()
            ->where('is_active', true)
            ->with(['toolTypes' => function ($query) use ($availableStatusIds, $depotId) {
                $query->where('is_active', true)
                    ->with('specFields')
                    ->withCount(['items as available_count' => function ($q) use ($availableStatusIds, $depotId) {
                        $q->where('is_loanable', true)
                            ->whereIn('custom_status_id', $availableStatusIds)
                            ->when($depotId, fn ($q2) => $q2->where('depot_id', $depotId));
                    }])
                    ->withCount('items as total_count');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'available_count' => $category->toolTypes->sum('available_count'),
                    'total_count' => $category->toolTypes->sum('total_count'),
                    'tool_types' => $category->toolTypes->map(fn ($tt) => [
                        'id' => $tt->id,
                        'name' => $tt->name,
                        'slug' => $tt->slug,
                        'icon' => $tt->icon,
                        'image_path' => $tt->image_path,
                        'available_count' => $tt->available_count,
                        'total_count' => $tt->total_count,
                        'allow_waitlist' => $tt->allow_waitlist,
                        'spec_fields' => $tt->specFields,
                    ]),
                ];
            });

        return response()->json(['data' => $categories]);
    }

    public function items(Request $request, \App\Models\ToolType $toolType)
    {
        $items = Item::query()
            ->where('tool_type_id', $toolType->id)
            ->with(['status', 'depot', 'toolType', 'specValues.field'])
            ->when($request->integer('depot_id'), fn ($q, $depotId) => $q->where('depot_id', $depotId))
            ->get();

        return response()->json(['data' => $items]);
    }
}
