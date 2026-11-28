<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a single marketplace_channel_orders row counts toward native post-sell dashboard KPIs.
 */
class OmnichannelPostSellStatsScope
{
    public function __construct(
        private OmnichannelPostSellEligibility $eligibility,
        private MarketplaceChannelOrderOpsBucket $opsBucket,
    ) {}

    public function orderInScope(MarketplaceChannelOrder $order): bool
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return false;
        }

        if (! in_array(strtolower((string) $order->channel), $this->eligibility->allowedChannels(), true)) {
            return false;
        }

        if (! $this->eligibility->passesRollingWindow($order)) {
            return false;
        }

        if ($this->eligibility->gateMode() === 'awb_printed') {
            if (! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_at')
                || $order->awb_printed_at === null) {
                return false;
            }
        }

        $bucket = $this->opsBucket->opsBucketForOrderStatus(
            (string) $order->channel,
            $order->commerce_platform ?? null,
            $order->order_status,
            $order->awb_printed_at !== null,
        );

        return in_array($bucket, [
            MarketplaceChannelOrderOpsBucket::BUCKET_IN_TRANSIT,
            MarketplaceChannelOrderOpsBucket::BUCKET_COMPLETED,
        ], true);
    }

    public function scopeKey(): string
    {
        $channels = $this->eligibility->allowedChannels();
        sort($channels);

        return hash('sha256', json_encode([
            'gate' => $this->eligibility->gateMode(),
            'max_age_days' => (int) config('marketplace.post_sell.max_age_days', 30),
            'channels' => $channels,
        ], JSON_THROW_ON_ERROR));
    }

    public function earliestRollingAnchor(): Carbon
    {
        $maxDays = (int) config('marketplace.post_sell.max_age_days', 30);

        return Carbon::now()->subDays($maxDays)->startOfDay();
    }
}