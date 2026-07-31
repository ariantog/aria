<?php

namespace App\Observers;

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if ($transaction->status->value === 1) {
            UpdateTransactionSummaries::dispatch($transaction->id);
        }
    }

    public function updated(Transaction $transaction): void
    {
        if ($transaction->isDirty('status') && $transaction->status->value === 1) {
            UpdateTransactionSummaries::dispatch($transaction->id);
        }
    }
}
