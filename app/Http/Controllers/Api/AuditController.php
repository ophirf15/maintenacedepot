<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filtered($request);

        return response()->json([
            'data' => $query->orderByDesc('occurred_at')->paginate($request->integer('per_page', 50)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->orderByDesc('occurred_at')->limit(50000)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-'.now()->format('Ymd-His').'.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Occurred At', 'User', 'Event', 'Auditable', 'Description', 'IP Address']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    optional($row->occurred_at)->toDateTimeString(),
                    $row->actor_label,
                    $row->event,
                    $row->auditable_type ? class_basename($row->auditable_type).'#'.$row->auditable_id : null,
                    $row->description,
                    $row->ip_address,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function filtered(Request $request)
    {
        $query = AuditEvent::query()->with('user');

        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($auditableType = $request->string('auditable_type')->toString()) {
            $query->where('auditable_type', 'like', "%{$auditableType}%");
        }

        if ($from = $request->date('from')) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($until = $request->date('until')) {
            $query->where('occurred_at', '<=', $until);
        }

        return $query;
    }
}
