<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\CustomStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Ticket;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Relations required for the readable "who requested what" sentences. */
    private const REQUEST_RELATIONS = ['requester', 'onBehalfOf', 'property', 'lines.item.toolType', 'lines.toolType'];

    private const LOAN_RELATIONS = ['borrower', 'depot', 'property', 'items.item.toolType'];

    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->can('approve_requests') || $user->can('checkout_items')) {
            return response()->json(['data' => $this->adminStats()]);
        }

        return response()->json(['data' => $this->borrowerStats($user->id)]);
    }

    protected function adminStats(): array
    {
        $availableStatusIds = CustomStatus::query()->where('availability_effect', 'available')->pluck('id');

        return [
            // Split so each tile links to a list that really shows those rows:
            // "submitted" is the depot's to-do, the other is waiting on the borrower.
            'pending_requests' => BorrowRequest::query()->where('status', 'submitted')->count(),
            'awaiting_borrower_requests' => BorrowRequest::query()->where('status', 'pending_modification_accept')->count(),
            'active_loans' => Loan::query()->whereIn('status', ['reserved', 'checked_out', 'return_pending'])->count(),
            'overdue_loans' => Loan::query()->where('due_at', '<', now())->whereIn('status', ['checked_out', 'return_pending'])->count(),
            'available_items' => Item::query()->whereIn('custom_status_id', $availableStatusIds)->where('is_loanable', true)->count(),
            'total_items' => Item::query()->count(),
            'open_tickets' => Ticket::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'recent_requests' => BorrowRequest::query()->with(self::REQUEST_RELATIONS)->orderByDesc('id')->limit(5)->get(),
            'recent_loans' => Loan::query()->with(self::LOAN_RELATIONS)->orderByDesc('id')->limit(5)->get(),
        ];
    }

    protected function borrowerStats(int $userId): array
    {
        return [
            'my_requests' => BorrowRequest::query()
                ->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('on_behalf_of_id', $userId))
                ->count(),
            'my_active_loans' => Loan::query()->where('borrower_id', $userId)
                ->whereIn('status', ['reserved', 'checked_out', 'return_pending'])
                ->count(),
            'my_overdue_loans' => Loan::query()->where('borrower_id', $userId)
                ->where('due_at', '<', now())
                ->whereIn('status', ['checked_out', 'return_pending'])
                ->count(),
            'my_action_items' => BorrowRequest::query()
                ->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('on_behalf_of_id', $userId))
                ->whereIn('status', ['draft', 'pending_modification_accept'])
                ->count(),
            'recent_requests' => BorrowRequest::query()
                ->with(self::REQUEST_RELATIONS)
                ->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('on_behalf_of_id', $userId))
                ->orderByDesc('id')->limit(5)->get(),
            'recent_loans' => Loan::query()->with(self::LOAN_RELATIONS)
                ->where('borrower_id', $userId)->orderByDesc('id')->limit(5)->get(),
        ];
    }
}
