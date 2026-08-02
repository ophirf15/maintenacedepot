<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class MarkOverdueLoans extends Command
{
    protected $signature = 'depot:mark-overdue';

    protected $description = 'Flag overdue loans and notify borrowers';

    public function handle(NotificationDispatcher $notifications): int
    {
        $loans = Loan::query()
            ->with('borrower')
            ->whereIn('status', ['checked_out', 'return_pending'])
            ->where('due_at', '<', now())
            ->get();

        foreach ($loans as $loan) {
            $loan->update(['status' => 'overdue']);
            if ($loan->borrower) {
                $notifications->send('loan.overdue', $loan->borrower, [
                    'title' => 'Loan overdue',
                    'body' => "Loan {$loan->reference} is past due. Please return or request an extension.",
                ], '/loans/'.$loan->id);
            }
        }

        $this->info("Marked {$loans->count()} loans overdue.");

        return self::SUCCESS;
    }
}
