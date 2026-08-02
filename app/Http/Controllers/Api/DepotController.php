<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class DepotController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = Depot::query()->with(['property', 'parent'])->withCount('items');

        if ($propertyId = $request->integer('property_id')) {
            $query->where('property_id', $propertyId);
        }

        if ($request->has('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'parent_depot_id' => 'nullable|exists:depots,id',
            'name' => 'required|string|max:160',
            'code' => 'required|string|max:32|unique:depots,code',
            'type' => 'in:main,satellite',
            'is_pickup_point' => 'boolean',
            'is_return_point' => 'boolean',
            'address_line1' => 'nullable|string|max:190',
            'city' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'allow_cross_property_transfer' => 'boolean',
            'default_max_loan_days' => 'nullable|integer|min:1|max:365',
            'pickup_window_enabled' => 'boolean',
            'pickup_window_hours' => 'nullable|integer|min:1|max:720',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $depot = Depot::query()->create($data);

        $this->audit->log('created', $depot, null, $depot->toArray());

        return response()->json(['data' => $depot->load('property')], 201);
    }

    public function update(Request $request, Depot $depot)
    {
        $data = $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'parent_depot_id' => 'nullable|exists:depots,id',
            'name' => 'sometimes|required|string|max:160',
            'code' => 'sometimes|required|string|max:32|unique:depots,code,'.$depot->id,
            'type' => 'in:main,satellite',
            'is_pickup_point' => 'boolean',
            'is_return_point' => 'boolean',
            'address_line1' => 'nullable|string|max:190',
            'city' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'allow_cross_property_transfer' => 'boolean',
            'default_max_loan_days' => 'nullable|integer|min:1|max:365',
            'pickup_window_enabled' => 'boolean',
            'pickup_window_hours' => 'nullable|integer|min:1|max:720',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $old = $depot->toArray();
        $depot->update($data);

        $this->audit->log('updated', $depot, $old, $depot->toArray());

        return response()->json(['data' => $depot->fresh('property')]);
    }
}
