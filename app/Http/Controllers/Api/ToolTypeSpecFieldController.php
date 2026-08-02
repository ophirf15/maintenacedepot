<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ToolType;
use App\Models\ToolTypeSpecField;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ToolTypeSpecFieldController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(ToolType $toolType)
    {
        return response()->json([
            'data' => $toolType->specFields()->get(),
        ]);
    }

    public function store(Request $request, ToolType $toolType)
    {
        $data = $this->validated($request);
        $data['tool_type_id'] = $toolType->id;
        $data['key'] = $data['key'] ?? Str::slug($data['label'], '_');
        $data['sort_order'] = $data['sort_order'] ?? (($toolType->specFields()->max('sort_order') ?? 0) + 1);

        $field = ToolTypeSpecField::query()->create($data);

        $this->audit->log('created', $field, null, $field->toArray());

        return response()->json(['data' => $field], 201);
    }

    public function update(Request $request, ToolType $toolType, ToolTypeSpecField $specField)
    {
        $this->assertBelongs($toolType, $specField);

        $data = $this->validated($request, partial: true);
        $old = $specField->toArray();
        $specField->update($data);

        $this->audit->log('updated', $specField, $old, $specField->toArray());

        return response()->json(['data' => $specField->fresh()]);
    }

    public function destroy(ToolType $toolType, ToolTypeSpecField $specField)
    {
        $this->assertBelongs($toolType, $specField);

        $old = $specField->toArray();
        $specField->delete();

        $this->audit->log('deleted', $specField, $old, null);

        return response()->json(['ok' => true]);
    }

    protected function assertBelongs(ToolType $toolType, ToolTypeSpecField $specField): void
    {
        abort_unless($specField->tool_type_id === $toolType->id, 404);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'key' => 'nullable|string|max:64',
            'label' => "{$required}|string|max:120",
            'unit' => 'nullable|string|max:32',
            'field_type' => 'nullable|in:number,text,select',
            'options' => 'nullable|array',
            'options.*' => 'string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_filterable' => 'boolean',
        ]);
    }
}
