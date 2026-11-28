<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility for omnichannel native SELL posting from marketplace_channel_orders.
 */
class OmnichannelPostSellEligibility
{
    public function __construct(
        private MarketplaceChannelOrderOpsBucket $opsBucket,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
        private OmnichannelSettingStore $settings,
        private MarketplaceSellPostGuard $sellPostGuard,
    ) {}

    public function masterEnabled(): bool
    {
        return (bool) config('marketplace.post_sell.enabled', false);
    }

    public function configDryRun(): bool
    {
        return (bool) config('marketplace.post_sell.dry_run', false);
    }

    public function gateMode(): string
    {
        return $this->settings->resolvePostSellGate();
    }

    /**
     * @return list<string>
     */
    public function allowedChannels(): array
    {
        $channels = config('marketplace.post_sell.channels', ['shopee']);
        if (! is_array($channels) || $channels === []) {
            return ['shopee'];
        }

        return array_values(array_unique(array_map('strtolower', $channels)));
    }

    public function passesChannelAllowlist(MarketplaceChannelOrder $order): bool
    {
        return in_array(strtolower((string) $order->channel), $this->allowedChannels(), true);
    }

    public function passesRollingWindow(MarketplaceChannelOrder $order): bool
    {
        $maxDays = (int) config('marketplace.post_sell.max_age_days', 30);
        $anchor = $order->marketplace_ordered_at ?? $order->last_event_at ?? $order->first_received_at;
        if ($anchor === null) {
            return false;
        }

        $earliest = Carbon::now()->subDays($maxDays)->startOfDay();

        return Carbon::parse($anchor)->greaterThanOrEqualTo($earliest);
    }

    public function alreadyPosted(MarketplaceChannelOrder $order): bool
    {
        if ($this->sellPostGuard->nativeAlreadyPosted($order)) {
            return true;
        }

        $invoice = $this->resolveInvoice($order);

        return $this->sellPostGuard->jubelioQueueBlocksNative($invoice, $order->order_ref);
    }

    public function passesGate(MarketplaceChannelOrder $order): bool
    {
        if ($this->isCancelled($order)) {
            return false;
        }

        if ($this->gateMode() === 'awb_printed') {
            return $this->passesAwbPrintedGate($order);
        }

        return $this->passesShippedGate($order);
    }

    public function rejectReason(MarketplaceChannelOrder $order): ?string
    {
        if (! $this->passesChannelAllowlist($order)) {
            return 'Channel tidak diizinkan untuk native post.';
        }
        if ($this->alreadyPosted($order)) {
            return 'Transaction sudah ada.';
        }
        if (! $this->passesRollingWindow($order)) {
            return 'Order di luar rolling window.';
        }
        if ($this->isCancelled($order)) {
            return 'Order dibatalkan.';
        }
        if (! $this->passesGate($order)) {
            return $this->gateMode() === 'awb_printed'
                ? 'Order belum memenuhi gate AWB printed.'
                : 'Order belum memenuhi gate shipped.';
        }
        if ((int) ($order->aria_warehouse_id ?? 0) <= 0) {
            return 'Gudang Aria belum dipetakan.';
        }

        return null;
    }

    public function isEligibleForPosting(MarketplaceChannelOrder $order): bool
    {
        return $this->rejectReason($order) === null;
    }

    public function resolveInvoice(MarketplaceChannelOrder $order): string
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

    private function passesShippedGate(MarketplaceChannelOrder $order): bool
    {
        $bucket = $this->opsBucket->opsBucketForOrderStatus(
            (string) $order->channel,
            $order->commerce_platform,
            $order->order_status,
            $order->awb_printed_at !== null,
        );

        return in_array($bucket, [
            MarketplaceChannelOrderOpsBucket::BUCKET_IN_TRANSIT,
            MarketplaceChannelOrderOpsBucket::BUCKET_COMPLETED,
        ], true);
    }

    private function passesAwbPrintedGate(MarketplaceChannelOrder $order): bool
    {
        if (! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_at')) {
            return false;
        }

        if ($order->awb_printed_at === null) {
            return false;
        }

        $bucket = $this->opsBucket->opsBucketForOrderStatus(
            (string) $order->channel,
            $order->commerce_platform,
            $order->order_status,
            true,
        );

        if ($bucket === null) {
            return true;
        }

        return ! in_array($bucket, [
            MarketplaceChannelOrderOpsBucket::BUCKET_CANCELLED,
            MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED,
        ], true);
    }

    private function isCancelled(MarketplaceChannelOrder $order): bool
    {
        $bucket = $this->opsBucket->opsBucketForOrderStatus(
            (string) $order->channel,
            $order->commerce_platform,
            $order->order_status,
            $order->awb_printed_at !== null,
        );

        return $bucket === MarketplaceChannelOrderOpsBucket::BUCKET_CANCELLED;
    }
}