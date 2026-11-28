<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioOrderSyncStatus;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\Jubelio\JubelioSellReturnGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Mutual exclusion between omnichannel native RETURN post and Jubelio inbound RETURN cron.
 */
class MarketplaceReturnPostGuard
{
    public function __construct(
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private JubelioSellReturnGuard $sellReturnGuard,
        private MarketplaceSellPostGuard $sellPostGuard,
    ) {}

    public function resolveReturnInvoice(string $sellInvoice): string
    {
        $sellInvoice = trim($sellInvoice);
        if ($sellInvoice === '') {
            return '';
        }

        if (str_starts_with(strtoupper($sellInvoice), 'RT-')) {
            return $sellInvoice;
        }

        return 'RT-'.$sellInvoice;
    }

    public function returnInvoiceTaken(string $returnInvoice, ?int $exceptJubelioOrderId = null): bool
    {
        if ($returnInvoice === '') {
            return false;
        }

        if (Transaction::query()
            ->where('type', Transaction::TYPE_RETURN)
            ->where('invoice', $returnInvoice)
            ->exists()) {
            return true;
        }

        return Jubelioorder::query()
            ->where('type', 'RETURN')
            ->where('invoice', $returnInvoice)
            ->when($exceptJubelioOrderId !== null, fn ($q) => $q->where('id', '!=', $exceptJubelioOrderId))
            ->where('status', 2)
            ->where('error_type', JubelioOrderSyncStatus::SUCCESS)
            ->exists();
    }

    public function sellReturnAlreadyPosted(string $sellInvoice, ?int $exceptJubelioOrderId = null): bool
    {
        return $this->sellReturnGuard->sellAlreadyReturned($sellInvoice, $exceptJubelioOrderId);
    }

    public function nativeReturnAlreadyPosted(MarketplaceChannelOrder $order): bool
    {
        if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')
            && (int) ($order->posted_return_transaction_id ?? 0) > 0) {
            return true;
        }

        $sellInvoice = $this->sellPostGuard->resolveNativeInvoice($order);
        if ($sellInvoice === '') {
            return false;
        }

        return $this->sellReturnGuard->returnPostedForSell($sellInvoice);
    }

    public function jubelioReturnQueueBlocksNative(string $sellInvoice): bool
    {
        if ($sellInvoice === '') {
            return false;
        }

        return $this->sellReturnGuard->pendingJubelioReturnQueue($sellInvoice);
    }

    public function shouldSkipNativeReturnPost(MarketplaceChannelOrder $order, string $sellInvoice, string $returnInvoice): bool
    {
        return $this->nativeReturnAlreadyPosted($order)
            || $this->jubelioReturnQueueBlocksNative($sellInvoice)
            || $this->sellReturnAlreadyPosted($sellInvoice)
            || $this->returnInvoiceTaken($returnInvoice);
    }

    public function existingReturnTransactionId(string $returnInvoice): ?int
    {
        if ($returnInvoice === '') {
            return null;
        }

        $id = Transaction::query()
            ->where('type', Transaction::TYPE_RETURN)
            ->where('invoice', $returnInvoice)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function existingReturnForSellTransactionId(string $sellInvoice): ?int
    {
        if ($sellInvoice === '') {
            return null;
        }

        $returnInvoice = $this->resolveReturnInvoice($sellInvoice);
        $srInvoice = str_starts_with(strtoupper($sellInvoice), 'SR-')
            ? $sellInvoice
            : 'SR-'.$sellInvoice;

        $id = Transaction::query()
            ->where('type', Transaction::TYPE_RETURN)
            ->where(function ($query) use ($sellInvoice, $returnInvoice, $srInvoice) {
                $query->where('description', $sellInvoice)
                    ->orWhere('invoice', $sellInvoice)
                    ->orWhere('invoice', $returnInvoice)
                    ->orWhere('invoice', $srInvoice);
            })
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function backfillChannelOrderReturnPosted(MarketplaceChannelOrder $order, int $transactionId): void
    {
        if ($transactionId <= 0
            || ! Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
            return;
        }

        if ((int) ($order->posted_return_transaction_id ?? 0) > 0) {
            return;
        }

        $order->forceFill([
            'posted_return_transaction_id' => $transactionId,
            'posted_return_at' => $order->posted_return_at ?? Carbon::now(),
        ])->save();
    }

    /**
     * When RETURN for a sell invoice already exists, mark the Jubelio queue row finished and backfill native channel order.
     *
     * @return array{success: bool, message: string, transaction_id: ?int}
     */
    public function finishJubelioReturnOnExistingReturn(
        Jubelioorder $order,
        int $runCount,
        ?int $executedByUserId,
        string $sellInvoice,
        ?string $returnInvoice = null,
    ): array {
        $sellInvoice = trim($sellInvoice);
        $returnInvoice = trim((string) ($returnInvoice ?? $order->invoice ?? ''));
        $transactionId = $this->existingReturnForSellTransactionId($sellInvoice);
        if ($transactionId === null && $returnInvoice !== '') {
            $transactionId = $this->existingReturnTransactionId($returnInvoice);
        }

        if ($sellInvoice !== '') {
            $channelOrder = $this->sellPostGuard->findChannelOrderByInvoice($sellInvoice);
            if ($channelOrder !== null && $transactionId !== null) {
                $this->backfillChannelOrderReturnPosted($channelOrder, $transactionId);
            }
        }

        $message = $transactionId !== null
            ? "Transaksi sudah ada (RETURN #{$transactionId})."
            : 'Transaksi retur sudah ada.';

        $order->update([
            'run_count' => $runCount,
            'error_type' => JubelioOrderSyncStatus::SUCCESS,
            'error' => $message,
            'stock_error_items' => null,
            'status' => 2,
            'execute_by' => $executedByUserId ?? 0,
            'source_sell_invoice' => $sellInvoice !== '' ? $sellInvoice : $order->source_sell_invoice,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'transaction_id' => $transactionId,
        ];
    }

    public function reconcileNativeSkipBecauseReturnExists(MarketplaceChannelOrder $order, string $sellInvoice, string $returnInvoice): void
    {
        $transactionId = $this->existingReturnForSellTransactionId($sellInvoice);
        if ($transactionId !== null) {
            $this->backfillChannelOrderReturnPosted($order, $transactionId);
        }

        Jubelioorder::query()
            ->where('type', 'RETURN')
            ->where(function ($query) use ($sellInvoice, $returnInvoice) {
                $query->where('source_sell_invoice', $sellInvoice)
                    ->orWhere('invoice', $returnInvoice);
            })
            ->where(function ($query): void {
                $query->where('status', 0)
                    ->orWhere('error_type', JubelioOrderSyncStatus::ERROR_DUPLICATE);
            })
            ->each(function (Jubelioorder $jubelioOrder) use ($sellInvoice): void {
                $this->finishJubelioReturnOnExistingReturn(
                    $jubelioOrder,
                    (int) $jubelioOrder->run_count,
                    null,
                    $sellInvoice,
                    (string) $jubelioOrder->invoice,
                );
            });
    }

    public function jubelioBlockedByNativeReturn(string $sellInvoice): bool
    {
        if ($sellInvoice === '') {
            return false;
        }

        if ($this->sellReturnGuard->returnPostedForSell($sellInvoice)) {
            return true;
        }

        $channelOrder = $this->sellPostGuard->findChannelOrderByInvoice($sellInvoice);
        if ($channelOrder !== null
            && Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')
            && (int) ($channelOrder->posted_return_transaction_id ?? 0) > 0) {
            return true;
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_RETURN)
            ->where('submit_type', Transaction::SUBMIT_TYPE_MARKETPLACE)
            ->where('description', $sellInvoice)
            ->exists();
    }

    public function linkedSellExists(string $sellInvoice): bool
    {
        return $this->sellInvoiceGuard->sellInvoiceInTransactions($sellInvoice);
    }
}