<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function myNotifications(Request $request)
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function matrixGet()
    {
        $types = NotificationType::query()->with('settings')->orderBy('group')->orderBy('name')->get();

        return response()->json(['data' => $types]);
    }

    public function matrixUpdate(Request $request)
    {
        $data = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.notification_type_id' => 'required|exists:notification_types,id',
            'entries.*.channel' => 'required|in:in_app,mail,sms',
            'entries.*.is_enabled' => 'required|boolean',
            'entries.*.scope_type' => 'nullable|in:global,property',
            'entries.*.scope_id' => 'nullable|integer',
        ]);

        foreach ($data['entries'] as $entry) {
            NotificationSetting::query()->updateOrCreate(
                [
                    'scope_type' => $entry['scope_type'] ?? 'global',
                    'scope_id' => $entry['scope_id'] ?? null,
                    'notification_type_id' => $entry['notification_type_id'],
                    'channel' => $entry['channel'],
                ],
                [
                    'is_enabled' => $entry['is_enabled'],
                    'updated_by' => $request->user()->id,
                ]
            );
        }

        return response()->json(['data' => NotificationType::query()->with('settings')->get()]);
    }
}
