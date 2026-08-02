<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomStatus;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomStatusController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = CustomStatus::query();

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
            'name' => 'required|string|max:120',
            'availability_effect' => 'required|in:available,unavailable,in_use',
            'color' => 'nullable|string|max:9',
            'icon' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_system'] = false;

        $status = CustomStatus::query()->create($data);

        $this->audit->log('created', $status, null, $status->toArray());

        return response()->json(['data' => $status], 201);
    }

    public function update(Request $request, CustomStatus $customStatus)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'availability_effect' => 'sometimes|required|in:available,unavailable,in_use',
            'color' => 'nullable|string|max:9',
            'icon' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($customStatus->is_system) {
            unset($data['availability_effect']);
        }

        $old = $customStatus->toArray();
        $customStatus->update($data);

        $this->audit->log('updated', $customStatus, $old, $customStatus->toArray());

        return response()->json(['data' => $customStatus->fresh()]);
    }
}
