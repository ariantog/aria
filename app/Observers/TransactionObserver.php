<?php

namespace App\Observers;

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if ((int) $transaction->status === Transaction::STATUS_COMPLETED) {
            UpdateTransactionSummaries::dispatch($transaction->id);
        }
    }

    public function updated(Transaction $transaction): void
    {
        if ($transaction->isDirty('status') && (int) $transaction->status === Transaction::STATUS_COMPLETED) {
            UpdateTransactionSummaries::dispatch($transaction->id);
        }
    }
}
