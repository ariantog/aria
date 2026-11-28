<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use Illuminate\Support\Facades\Cache;

class OmnichannelCompareDashboardStats
{
    public function __construct(
        private OmnichannelDashboardCacheBuster $buster,
    ) {}

    /**
     * @return array{
     *     matched: int,
     *     mismatch: int,
     *     jubelio_missing: int,
     *     pending: int,
     *     line_mismatch: int,
     *     jubelio_pending_sell: int,
     * }
     */
    public function counts(): array
    {
        $ttl = max(30, (int) config('marketplace.dashboard_cache.compare_stats_ttl_seconds', 120));

        return Cache::remember(OmnichannelDashboardCacheBuster::COMPARE_STATS_CACHE_KEY, $ttl, function () {
            return [
                'matched' => MarketplaceOrderShadow::query()
                    ->where('compare_status', MarketplaceOrderShadow::COMPARE_MATCHED)
                    ->count(),
                'mismatch' => MarketplaceOrderShadow::query()
                    ->where('compare_status', MarketplaceOrderShadow::COMPARE_MISMATCH)
                    ->count(),
                'jubelio_missing' => MarketplaceOrderShadow::query()
                    ->where('compare_status', MarketplaceOrderShadow::COMPARE_JUBELIO_MISSING)
                    ->count(),
                'pending' => MarketplaceOrderShadow::query()
                    ->where('compare_status', MarketplaceOrderShadow::COMPARE_PENDING)
                    ->count(),
                'line_mismatch' => MarketplaceOrderShadow::query()
                    ->where('line_compare_status', MarketplaceOrderShadow::LINE_COMPARE_MISMATCH)
                    ->count(),
                'jubelio_pending_sell' => Jubelioorder::query()
                    ->where('type', 'SELL')
                    ->where('status', 0)
                    ->count(),
            ];
        });
    }
}