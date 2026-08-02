<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BorrowRequest;
use App\Models\Loan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesDepotAccess
{
    protected function assertCanAccessLoan(User $user, Loan $loan): void
    {
        if ($user->can('checkout_items')) {
            return;
        }

        if ((int) $loan->borrower_id === (int) $user->id) {
            return;
        }

        throw new AuthorizationException('You do not have access to this loan.');
    }

    protected function assertCanAccessBorrowRequest(User $user, BorrowRequest $borrowRequest): void
    {
        if ($user->can('approve_requests')) {
            return;
        }

        if ((int) $borrowRequest->requester_id === (int) $user->id
            || (int) $borrowRequest->on_behalf_of_id === (int) $user->id) {
            return;
        }

        throw new AuthorizationException('You do not have access to this request.');
    }

    protected function assertCanAccessTicket(User $user, Ticket $ticket): void
    {
        if ($user->can('manage_tickets')) {
            return;
        }

        if ((int) $ticket->reported_by === (int) $user->id) {
            return;
        }

        throw new AuthorizationException('You do not have access to this ticket.');
    }
}
