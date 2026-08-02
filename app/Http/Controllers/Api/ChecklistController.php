<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = ChecklistTemplate::query()->with('items');

        if ($toolTypeId = $request->integer('tool_type_id')) {
            $query->where('tool_type_id', $toolTypeId);
        }

        if ($context = $request->string('context')->toString()) {
            $query->where('context', $context);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $template = DB::transaction(function () use ($data) {
            $template = ChecklistTemplate::query()->create([
                'tool_type_id' => $data['tool_type_id'] ?? null,
                'name' => $data['name'],
                'context' => $data['context'],
                'is_required' => $data['is_required'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncItems($template, $data['items'] ?? []);

            return $template;
        });

        $this->audit->log('created', $template, null, $template->toArray());

        return response()->json(['data' => $template->load('items')], 201);
    }

    public function show(ChecklistTemplate $checklist)
    {
        return response()->json(['data' => $checklist->load('items')]);
    }

    public function update(Request $request, ChecklistTemplate $checklist)
    {
        $data = $this->validated($request, isUpdate: true);

        $old = $checklist->toArray();

        DB::transaction(function () use ($checklist, $data) {
            $checklist->update(array_filter([
                'tool_type_id' => $data['tool_type_id'] ?? $checklist->tool_type_id,
                'name' => $data['name'] ?? $checklist->name,
                'context' => $data['context'] ?? $checklist->context,
                'is_required' => $data['is_required'] ?? $checklist->is_required,
                'is_active' => $data['is_active'] ?? $checklist->is_active,
            ], fn ($v) => $v !== null));

            if (isset($data['items'])) {
                $checklist->items()->delete();
                $this->syncItems($checklist, $data['items']);
            }
        });

        $this->audit->log('updated', $checklist, $old, $checklist->fresh()->toArray());

        return response()->json(['data' => $checklist->fresh('items')]);
    }

    public function destroy(ChecklistTemplate $checklist)
    {
        $old = $checklist->toArray();
        $checklist->delete();

        $this->audit->log('deleted', $checklist, $old, null);

        return response()->json(['ok' => true]);
    }

    protected function syncItems(ChecklistTemplate $template, array $items): void
    {
        foreach ($items as $index => $item) {
            ChecklistItem::query()->create([
                'checklist_template_id' => $template->id,
                'label' => $item['label'],
                'response_type' => $item['response_type'] ?? 'pass_fail',
                'options' => $item['options'] ?? null,
                'is_required' => $item['is_required'] ?? true,
                'sort_order' => $item['sort_order'] ?? $index,
            ]);
        }
    }

    protected function validated(Request $request, bool $isUpdate = false): array
    {
        $prefix = $isUpdate ? 'sometimes|' : '';

        return $request->validate([
            'tool_type_id' => 'nullable|exists:tool_types,id',
            'name' => $prefix.'required|string|max:190',
            'context' => $prefix.'required|in:checkout,return,inspection',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'items' => 'nullable|array',
            'items.*.label' => 'required_with:items|string|max:255',
            'items.*.response_type' => 'nullable|in:pass_fail,text,number,rating',
            'items.*.options' => 'nullable|array',
            'items.*.is_required' => 'boolean',
            'items.*.sort_order' => 'nullable|integer',
        ]);
    }
}
