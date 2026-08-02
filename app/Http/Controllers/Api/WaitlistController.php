<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ToolType;
use App\Models\WaitlistEntry;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $entries = WaitlistEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'waiting')
            ->with(['toolType.category', 'item.toolType', 'item.specValues.field'])
            ->orderBy('desired_from')
            ->orderBy('position')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request)
    {
        if (! $this->settings->get('features', 'waitlist_enabled', true)) {
            return response()->json([
                'message' => 'The waiting list is turned off right now.',
            ], 422);
        }

        $data = $request->validate([
            'tool_type_id' => 'required|exists:tool_types,id',
            'item_id' => 'nullable|exists:items,id',
            'property_id' => 'required|exists:properties,id',
            'desired_from' => 'required|date',
            'desired_until' => 'required|date|after:desired_from',
        ]);

        $toolType = ToolType::query()->findOrFail($data['tool_type_id']);

        if (! $toolType->allow_waitlist) {
            return response()->json([
                'message' => 'This tool does not use a waiting list.',
            ], 422);
        }

        $alreadyWaiting = WaitlistEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('tool_type_id', $toolType->id)
            ->where('item_id', $data['item_id'] ?? null)
            ->where('status', 'waiting')
            ->exists();

        if ($alreadyWaiting) {
            return response()->json([
                'message' => 'You are already on the waiting list for this tool.',
            ], 422);
        }

        $position = WaitlistEntry::query()
            ->where('tool_type_id', $toolType->id)
            ->where('item_id', $data['item_id'] ?? null)
            ->where('status', 'waiting')
            ->max('position') + 1;

        $entry = WaitlistEntry::query()->create([
            'property_id' => $data['property_id'],
            'user_id' => $request->user()->id,
            'item_id' => $data['item_id'] ?? null,
            'tool_type_id' => $toolType->id,
            'position' => $position ?: 1,
            'desired_from' => $data['desired_from'],
            'desired_until' => $data['desired_until'],
            'status' => 'waiting',
        ]);

        $this->audit->log('waitlist_joined', $entry, null, $entry->toArray());

        return response()->json([
            'data' => $entry->load(['toolType', 'item']),
        ], 201);
    }

    public function destroy(Request $request, WaitlistEntry $waitlist)
    {
        abort_unless($waitlist->user_id === $request->user()->id, 403);

        $old = $waitlist->toArray();
        $waitlist->update(['status' => 'cancelled']);

        $this->audit->log('waitlist_left', $waitlist, $old, $waitlist->toArray());

        return response()->json(['ok' => true]);
    }
}
