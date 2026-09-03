<?php

namespace App\Observers;

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Transaction;
use App\Services\TransactionService;

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
        if ($transaction->wasChanged('status') && (int) $transaction->status === Transaction::STATUS_COMPLETED) {
            UpdateTransactionSummaries::dispatch($transaction->id);
        }

        if (TransactionService::isPostingRunningBalances()) {
            return;
        }

        if (
            (int) $transaction->status !== Transaction::STATUS_COMPLETED
            && (int) $transaction->getOriginal('status') !== Transaction::STATUS_COMPLETED
        ) {
            return;
        }

        if (! $transaction->wasChanged([
            'date',
            'total',
            'type',
            'sender_id',
            'receiver_id',
            'sender_type',
            'receiver_type',
            'status',
        ])) {
            return;
        }

        app(TransactionService::class)->recalculateAffectedRunningBalances($transaction, includeOriginal: true);
    }

    public function deleted(Transaction $transaction): void
    {
        if (TransactionService::isPostingRunningBalances()) {
            return;
        }

        app(TransactionService::class)->recalculateAffectedRunningBalances(
            $transaction,
            includeOriginal: false,
            excludeId: (int) $transaction->id,
        );
    }
}
