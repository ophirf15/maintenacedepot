<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Ticket;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim($request->string('q')->toString());
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $limit = min(max($request->integer('limit', 12), 1), 25);
        $like = '%'.$q.'%';
        $hits = [];

        $items = Item::query()
            ->with(['toolType.category', 'status', 'depot'])
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('asset_tag', 'like', $like)
                    ->orWhere('numeric_code', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('supplier_name', 'like', $like)
                    ->orWhere('supplier_part_number', 'like', $like)
                    ->orWhereHas('toolType', fn ($tq) => $tq->where('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $canManageInventory = $user->can('manage_inventory') || $user->can('manage_catalog');

        foreach ($items as $item) {
            $hits[] = [
                'entity_type' => 'item',
                'entity_id' => (string) $item->id,
                'title' => $item->label,
                'snippet' => trim(($item->asset_tag ?: '').' · '.($item->depot?->name ?: 'No depot')),
                'href' => $canManageInventory
                    ? '/inventory/items/'.$item->id
                    : '/items/'.$item->id,
                'icon' => 'package',
                'rank' => 100,
            ];
        }

        if ($user->can('borrow_items')) {
            $requests = BorrowRequest::query()
                ->with(['property', 'pickupDepot'])
                ->where(function ($query) use ($user) {
                    if (! $user->can('approve_requests')) {
                        $query->where(function ($scoped) use ($user) {
                            $scoped->where('requester_id', $user->id)
                                ->orWhere('on_behalf_of_id', $user->id);
                        });
                    }
                })
                ->where(function ($query) use ($like, $q) {
                    $query->where('reference', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('status', 'like', $like);
                    if (ctype_digit($q)) {
                        $query->orWhere('id', (int) $q);
                    }
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($requests as $requestRow) {
                $hits[] = [
                    'entity_type' => 'borrow_request',
                    'entity_id' => (string) $requestRow->id,
                    'title' => $requestRow->summary ?: ($requestRow->reference ?: 'Request #'.$requestRow->id),
                    'snippet' => trim(($requestRow->reference ?: '').' · '.($requestRow->status ?: '')),
                    'href' => '/requests/'.$requestRow->id,
                    'icon' => 'clipboard',
                    'rank' => 80,
                ];
            }
        }

        $loans = Loan::query()
            ->with(['borrower', 'depot'])
            ->when(! $user->can('checkout_items'), function ($query) use ($user) {
                $query->where('borrower_id', $user->id);
            })
            ->where(function ($query) use ($like, $q) {
                $query->where('reference', 'like', $like)
                    ->orWhere('status', 'like', $like);
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($loans as $loan) {
            $hits[] = [
                'entity_type' => 'loan',
                'entity_id' => (string) $loan->id,
                'title' => $loan->summary ?: ($loan->reference ?: 'Loan #'.$loan->id),
                'snippet' => trim(($loan->reference ?: '').' · '.($loan->status ?: '')),
                'href' => '/loans/'.$loan->id,
                'icon' => 'handshake',
                'rank' => 70,
            ];
        }

        $tickets = Ticket::query()
            ->when(! $user->can('manage_tickets'), function ($query) use ($user) {
                $query->where('reported_by', $user->id);
            })
            ->where(function ($query) use ($like, $q) {
                $query->where('reference', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($tickets as $ticket) {
            $hits[] = [
                'entity_type' => 'ticket',
                'entity_id' => (string) $ticket->id,
                'title' => $ticket->title ?: ($ticket->reference ?: 'Ticket #'.$ticket->id),
                'snippet' => trim(($ticket->reference ?: '').' · '.($ticket->status ?: '')),
                'href' => '/tickets/'.$ticket->id,
                'icon' => 'ticket',
                'rank' => 60,
            ];
        }

        usort($hits, fn ($a, $b) => ($b['rank'] <=> $a['rank']) ?: strcmp($a['title'], $b['title']));

        return response()->json([
            'data' => array_slice($hits, 0, $limit),
        ]);
    }
}
