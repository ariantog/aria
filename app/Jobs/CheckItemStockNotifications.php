<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\ItemStockNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckItemStockNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transactionId) {}

    public function handle(ItemStockNotificationService $service): void
    {
        $transaction = Transaction::with('details')->find($this->transactionId);
        if (! $transaction) {
            return;
        }

        $service->checkAfterSell($transaction);
    }
}
