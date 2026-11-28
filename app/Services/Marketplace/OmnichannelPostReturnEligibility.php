<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility for omnichannel native RETURN posting from marketplace_channel_orders.
 */
class OmnichannelPostReturnEligibility
{
    public function __construct(
        private MarketplaceChannelOrderOpsBucket $opsBucket,
        private OmnichannelPostSellEligibility $sellEligibility,
        private MarketplaceReturnPostGuard $returnPostGuard,
    ) {}

    public function masterEnabled(): bool
    {
        return (bool) config('marketplace.post_return.enabled', false);
    }

    public function configDryRun(): bool
    {
        return (bool) config('marketplace.post_return.dry_run', false);
    }

    /**
     * @return list<string>
     */
    public function allowedChannels(): array
    {
        $channels = config('marketplace.post_return.channels', ['shopee']);
        if (! is_array($channels) || $channels === []) {
            return ['shopee'];
        }

        return array_values(array_unique(array_map('strtolower', $channels)));
    }

    public function passesChannelAllowlist(MarketplaceChannelOrder $order): bool
    {
        $allowed = $this->allowedChannels();
        $channel = strtolower((string) $order->channel);
        $isTokopedia = strtoupper((string) ($order->commerce_platform ?? '')) === 'TOKOPEDIA';

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            return in_array(MarketplaceWebhookRecorder::CHANNEL_SHOPEE, $allowed, true);
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            if ($isTokopedia) {
                return in_array('tokopedia', $allowed, true) || in_array(MarketplaceWebhookRecorder::CHANNEL_TIKTOK, $allowed, true);
            }

            return in_array(MarketplaceWebhookRecorder::CHANNEL_TIKTOK, $allowed, true);
        }

        return in_array($channel, $allowed, true);
    }

    public function requirePhysicalVerify(): bool
    {
        return (bool) config('marketplace.post_return.require_physical_verify', true);
    }

    public function passesPhysicalVerify(MarketplaceChannelOrder $order): bool
    {
        if (! $this->requirePhysicalVerify()) {
            return true;
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_verified_at')) {
            return false;
        }

        return $order->return_verified_at !== null;
    }

    public function passesRollingWindow(MarketplaceChannelOrder $order): bool
    {
        $maxDays = (int) config('marketplace.post_return.max_age_days', 30);
        $anchor = $order->marketplace_ordered_at ?? $order->last_event_at ?? $order->first_received_at;
        if ($anchor === null) {
            return false;
        }

        $earliest = Carbon::now()->subDays($maxDays)->startOfDay();

        return Carbon::parse($anchor)->greaterThanOrEqualTo($earliest);
    }

    public function resolveSellInvoice(MarketplaceChannelOrder $order): string
    {
        return $this->sellEligibility->resolveInvoice($order);
    }

    public function resolveReturnInvoice(MarketplaceChannelOrder $order): string
    {
        return $this->returnPostGuard->resolveReturnInvoice($this->resolveSellInvoice($order));
    }

    public function passesReturnGate(MarketplaceChannelOrder $order): bool
    {
        $channel = strtolower((string) $order->channel);
        $status = strtoupper(trim((string) $order->order_status));

        $extra = match ($channel) {
            MarketplaceWebhookRecorder::CHANNEL_SHOPEE => array_map('strtoupper', (array) config('marketplace.post_return.shopee_statuses', [])),
            MarketplaceWebhookRecorder::CHANNEL_TIKTOK => array_map('strtoupper', (array) config('marketplace.post_return.tiktok_statuses', [])),
            default => [],
        };

        if (in_array($status, $extra, true)) {
            return true;
        }

        $bucket = $this->opsBucket->opsBucketForOrderStatus(
            (string) $order->channel,
            $order->commerce_platform,
            $order->order_status,
            $order->awb_printed_at !== null,
        );

        return $bucket === MarketplaceChannelOrderOpsBucket::BUCKET_CANCELLED;
    }

    public function alreadyPosted(MarketplaceChannelOrder $order): bool
    {
        $sellInvoice = $this->resolveSellInvoice($order);
        $returnInvoice = $this->resolveReturnInvoice($order);

        return $this->returnPostGuard->nativeReturnAlreadyPosted($order)
            || $this->returnPostGuard->sellReturnAlreadyPosted($sellInvoice)
            || $this->returnPostGuard->jubelioReturnQueueBlocksNative($sellInvoice)
            || $this->returnPostGuard->returnInvoiceTaken($returnInvoice);
    }

    public function rejectReason(MarketplaceChannelOrder $order): ?string
    {
        if (! $this->passesChannelAllowlist($order)) {
            return 'Channel tidak diizinkan untuk native return.';
        }
        if (! $this->passesRollingWindow($order)) {
            return 'Order di luar rolling window.';
        }
        if (! $this->passesReturnGate($order)) {
            return 'Order belum memenuhi gate return/cancelled.';
        }
        if (! $this->passesPhysicalVerify($order)) {
            return 'Belum diverifikasi penerimaan fisik.';
        }
        if ((int) ($order->aria_warehouse_id ?? 0) <= 0) {
            return 'Gudang Aria belum dipetakan.';
        }

        $sellInvoice = $this->resolveSellInvoice($order);
        if (! $this->returnPostGuard->linkedSellExists($sellInvoice)) {
            return 'Invoice jual tidak ditemukan.';
        }

        if ($this->alreadyPosted($order)) {
            return 'Transaksi retur sudah ada.';
        }

        return null;
    }

    public function isEligibleForPosting(MarketplaceChannelOrder $order): bool
    {
        return $this->rejectReason($order) === null;
    }

    /**
     * SQL filter statuses for batch candidate pre-filter.
     *
     * @return list<string>
     */
    public function candidateStatuses(): array
    {
        $shopee = array_map('strtoupper', (array) config('marketplace.post_return.shopee_statuses', []));
        $tiktok = array_map('strtoupper', (array) config('marketplace.post_return.tiktok_statuses', []));

        return array_values(array_unique(array_merge($shopee, $tiktok)));
    }
}