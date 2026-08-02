<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesDepotAccess;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\ItemStatusService;
use App\Services\ReferenceGenerator;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use AuthorizesDepotAccess;

    public function __construct(
        private AuditLogger $audit,
        private ReferenceGenerator $refs,
        private ItemStatusService $itemStatus,
    ) {}

    public function index(Request $request)
    {
        $query = Ticket::query()->with(['item.toolType', 'reporter', 'assignee']);
        $user = $request->user();

        if (! $user->can('manage_tickets')) {
            $query->where('reported_by', $user->id);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($severity = $request->string('severity')->toString()) {
            $query->where('severity', $severity);
        }

        if ($itemId = $request->integer('item_id')) {
            $query->where('item_id', $itemId);
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'loan_id' => 'nullable|exists:loans,id',
            'ticket_type' => 'required|in:defect,damage,inspection,other',
            'maintenance_type_id' => 'nullable|exists:maintenance_types,id',
            'title' => 'required|string|max:190',
            'description' => 'nullable|string',
            'severity' => 'in:low,medium,high,critical',
            'priority' => 'in:low,normal,high,urgent',
            'takes_out_of_service' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (! empty($data['loan_id'])) {
            $loan = \App\Models\Loan::query()->findOrFail($data['loan_id']);
            $this->assertCanAccessLoan($request->user(), $loan);
        }

        $canOos = $request->user()->can('manage_tickets')
            || $request->user()->can('manage_inventory');

        // Borrowers may report issues; only staff may force OOS / critical holds.
        if (! $canOos) {
            $data['takes_out_of_service'] = false;
            if (($data['severity'] ?? null) === 'critical') {
                $data['severity'] = 'high';
            }
            unset($data['assigned_to']);
        }

        $data['reference'] = $this->refs->make('TK');
        $data['reported_by'] = $request->user()->id;
        $data['status'] = 'open';

        $ticket = Ticket::query()->create($data);

        $this->audit->log('created', $ticket, null, $ticket->toArray());
        $this->applyServiceHold($ticket);

        return response()->json(['data' => $ticket->load(['item.toolType', 'reporter'])], 201);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->assertCanAccessTicket($request->user(), $ticket);

        $ticket->load(['item.toolType', 'reporter', 'assignee', 'workOrders', 'attachments']);

        return response()->json([
            'data' => $ticket,
            'photos' => $ticket->attachments->map(fn (Attachment $att) => [
                'id' => $att->id,
                'url' => PublicStorageUrl::path($att->path),
                'collection' => $att->collection,
                'original_name' => $att->original_name,
            ]),
        ]);
    }

    public function uploadPhoto(Request $request, Ticket $ticket)
    {
        $this->assertCanAccessTicket($request->user(), $ticket);

        $data = $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'collection' => 'nullable|in:damage,default',
        ]);

        $path = $request->file('photo')->store('tickets/'.$ticket->id, 'public');
        $file = $request->file('photo');

        $attachment = $ticket->attachments()->create([
            'collection' => $data['collection'] ?? 'damage',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log('photo_uploaded', $ticket, null, $attachment->toArray());

        return response()->json([
            'data' => $attachment,
            'url' => PublicStorageUrl::path($path),
        ], 201);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:190',
            'description' => 'nullable|string',
            'severity' => 'in:low,medium,high,critical',
            'priority' => 'in:low,normal,high,urgent',
            'status' => 'in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
            'takes_out_of_service' => 'boolean',
        ]);

        $old = $ticket->toArray();
        $ticket->update($data);

        $this->audit->log('updated', $ticket, $old, $ticket->toArray());
        $this->applyServiceHold($ticket);

        return response()->json(['data' => $ticket->fresh(['item.toolType', 'reporter', 'assignee', 'workOrders'])]);
    }

    public function resolve(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'resolution_code' => 'nullable|string|max:24',
            'resolution_notes' => 'nullable|string',
            'total_cost' => 'nullable|numeric|min:0',
            'restore_to_service' => 'boolean',
        ]);

        $old = $ticket->toArray();
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution_code' => $data['resolution_code'] ?? null,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'total_cost' => $data['total_cost'] ?? $ticket->total_cost,
        ]);

        if (($data['restore_to_service'] ?? false) && $ticket->item) {
            $this->itemStatus->restoreToService($ticket->item, "Fixed under {$ticket->reference}");
        }

        $this->audit->log('resolved', $ticket, $old, $ticket->toArray());

        return response()->json(['data' => $ticket->fresh(['item.toolType', 'reporter', 'assignee', 'workOrders'])]);
    }

    /**
     * A tool flagged unsafe (or marked out of service) must stop being borrowable
     * immediately, not just carry a flag on the ticket.
     */
    private function applyServiceHold(Ticket $ticket): void
    {
        if (! $ticket->item || in_array($ticket->status, ['resolved', 'closed'], true)) {
            return;
        }

        if ($ticket->takes_out_of_service || $ticket->severity === 'critical') {
            $this->itemStatus->takeOutOfService(
                $ticket->item,
                "Out of service for {$ticket->reference}: {$ticket->title}",
            );
        }
    }
}
