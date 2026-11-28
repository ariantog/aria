<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioOrderSyncStatus;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use Illuminate\Support\Facades\Schema;

/**
 * Mutual exclusion between omnichannel native SELL post and Jubelio inbound sell cron during cutover.
 */
class MarketplaceSellPostGuard
{
    public function __construct(
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

    public function invoiceTaken(string $invoice, ?int $exceptJubelioOrderId = null): bool
    {
        if ($invoice === '') {
            return false;
        }

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return true;
        }

        return Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->when($exceptJubelioOrderId !== null, fn ($q) => $q->where('id', '!=', $exceptJubelioOrderId))
            ->where('status', 2)
            ->where('error_type', JubelioOrderSyncStatus::SUCCESS)
            ->exists();
    }

    public function nativeAlreadyPosted(MarketplaceChannelOrder $order): bool
    {
        if (Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')
            && (int) ($order->posted_transaction_id ?? 0) > 0) {
            return true;
        }

        $invoice = $this->resolveNativeInvoice($order);
        if ($invoice === '') {
            return false;
        }

        return $this->sellInvoiceGuard->sellInvoiceInTransactions($invoice);
    }

    public function jubelioQueueBlocksNative(string $invoice, ?string $orderRef = null): bool
    {
        if ($invoice === '') {
            return false;
        }

        return Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->where(function ($query) {
                $query->where('status', 0)
                    ->orWhere(function ($success) {
                        $success->where('status', 2)
                            ->where('error_type', JubelioOrderSyncStatus::SUCCESS);
                    });
            })
            ->exists();
    }

    public function shouldSkipNativePost(MarketplaceChannelOrder $order, string $invoice): bool
    {
        return $this->nativeAlreadyPosted($order)
            || $this->jubelioQueueBlocksNative($invoice, $order->order_ref)
            || $this->invoiceTaken($invoice);
    }

    public function jubelioBlockedByNativePost(string $invoice): bool
    {
        if ($invoice === '') {
            return false;
        }

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return true;
        }

        $channelOrder = $this->findChannelOrderByInvoice($invoice);
        if ($channelOrder !== null
            && Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')
            && (int) ($channelOrder->posted_transaction_id ?? 0) > 0) {
            return true;
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice', $invoice)
            ->where('submit_type', Transaction::SUBMIT_TYPE_MARKETPLACE)
            ->exists();
    }

    public function findChannelOrderByInvoice(string $invoice): ?MarketplaceChannelOrder
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return null;
        }

        $invoice = trim($invoice);
        if ($invoice === '') {
            return null;
        }

        if (str_starts_with(strtoupper($invoice), 'TT-')) {
            $orderRef = substr($invoice, 3);

            return MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where('order_ref', $orderRef)
                ->orderByDesc('id')
                ->first();
        }

        if (preg_match('/^([A-Za-z]{2,10})-(.+)$/u', $invoice, $matches)) {
            $orderRef = $matches[2];

            return MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
                ->where('order_ref', $orderRef)
                ->orderByDesc('id')
                ->first();
        }

        return MarketplaceChannelOrder::query()
            ->where('order_ref', $invoice)
            ->orderByDesc('id')
            ->first();
    }

    public function resolveNativeInvoice(MarketplaceChannelOrder $order): string
    {
        $channel = strtolower((string) $order->channel);
        $orderRef = trim((string) $order->order_ref);

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            $shopId = is_numeric((string) $order->shop_ref) ? (int) $order->shop_ref : null;

            return $this->shopeeInvoicePrefix->invoiceForOrderSn($shopId, $orderRef);
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            return 'TT-'.$orderRef;
        }

        return $orderRef;
    }
}