<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = Property::query()->withCount(['users']);

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'code' => 'required|string|max:32|unique:properties,code',
            'address_line1' => 'nullable|string|max:190',
            'city' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:32',
            'contact_email' => 'nullable|email|max:190',
            'contact_phone' => 'nullable|string|max:32',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        $property = Property::query()->create($data);

        $this->audit->log('created', $property, null, $property->toArray());

        return response()->json(['data' => $property], 201);
    }

    public function update(Request $request, Property $property)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'code' => 'sometimes|required|string|max:32|unique:properties,code,'.$property->id,
            'address_line1' => 'nullable|string|max:190',
            'city' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:32',
            'contact_email' => 'nullable|email|max:190',
            'contact_phone' => 'nullable|string|max:32',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ]);

        $old = $property->toArray();
        $property->update($data);

        $this->audit->log('updated', $property, $old, $property->toArray());

        return response()->json(['data' => $property->fresh()]);
    }

    public function destroy(Property $property)
    {
        $old = $property->toArray();
        $property->delete();

        $this->audit->log('deleted', $property, $old, null);

        return response()->json(['ok' => true]);
    }
}
