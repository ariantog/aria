<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Contracts\MissedPushChannelReconciler;

class MarketplaceMissedPushReconcileService
{
    public function __construct(
        private ShopeeMissedPushReconcileService $shopee,
        private TikTokMissedPushReconcileStub $tiktokStub,
    ) {}

    /**
     * @return array<string, array{shops: int, orders_seen: int, gaps_queued: int, ingested: int, skipped: int, errors: list<string>}>
     */
    public function reconcile(
        ?string $channel = null,
        ?int $onlyShopId = null,
        ?int $days = null,
        bool $ingestGaps = true,
    ): array {
        $channel = strtolower(trim((string) ($channel ?? 'shopee')));

        /** @var list<MissedPushChannelReconciler> $reconcilers */
        $reconcilers = match ($channel) {
            'all' => [$this->shopee, $this->tiktokStub],
            'tiktok' => [$this->tiktokStub],
            default => [$this->shopee],
        };

        $results = [];
        foreach ($reconcilers as $reconciler) {
            if (! $reconciler->enabled()) {
                continue;
            }

            $shopFilter = $reconciler->channel() === MarketplaceWebhookRecorder::CHANNEL_SHOPEE
                ? $onlyShopId
                : null;

            $results[$reconciler->channel()] = $reconciler->reconcile($shopFilter, $days, $ingestGaps);
        }

        return $results;
    }
}