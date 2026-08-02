<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = CustomField::query();

        if ($entityType = $request->string('entity_type')->toString()) {
            $query->where('entity_type', $entityType);
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:48',
            'key' => 'required|string|max:64|alpha_dash',
            'label' => 'required|string|max:190',
            'help_text' => 'nullable|string|max:255',
            'field_type' => 'required|in:text,textarea,number,boolean,date,select,multiselect',
            'options' => 'nullable|array',
            'default_value' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $exists = CustomField::query()
            ->where('entity_type', $data['entity_type'])
            ->where('key', $data['key'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'A field with that key already exists for this entity type.'], 422);
        }

        $field = CustomField::query()->create($data);

        $this->audit->log('created', $field, null, $field->toArray());

        return response()->json(['data' => $field], 201);
    }

    public function update(Request $request, CustomField $customField)
    {
        $data = $request->validate([
            'label' => 'sometimes|required|string|max:190',
            'help_text' => 'nullable|string|max:255',
            'field_type' => 'sometimes|required|in:text,textarea,number,boolean,date,select,multiselect',
            'options' => 'nullable|array',
            'default_value' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $old = $customField->toArray();
        $customField->update($data);

        $this->audit->log('updated', $customField, $old, $customField->toArray());

        return response()->json(['data' => $customField->fresh()]);
    }
}
