<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Contracts\MissedPushChannelReconciler;

/**
 * TikTok missed-push reconcile deferred to a follow-up PR (same interface as Shopee).
 */
class TikTokMissedPushReconcileStub implements MissedPushChannelReconciler
{
    public function channel(): string
    {
        return MarketplaceWebhookRecorder::CHANNEL_TIKTOK;
    }

    public function enabled(): bool
    {
        return false;
    }

    public function reconcile(?int $onlyShopId = null, ?int $days = null, bool $ingestGaps = true): array
    {
        return [
            'shops' => 0,
            'orders_seen' => 0,
            'gaps_queued' => 0,
            'ingested' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }
}