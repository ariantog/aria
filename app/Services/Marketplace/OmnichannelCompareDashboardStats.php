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
            $matched = MarketplaceOrderShadow::COMPARE_MATCHED;
            $mismatch = MarketplaceOrderShadow::COMPARE_MISMATCH;
            $jubelioMissing = MarketplaceOrderShadow::COMPARE_JUBELIO_MISSING;
            $pending = MarketplaceOrderShadow::COMPARE_PENDING;
            $lineMismatch = MarketplaceOrderShadow::LINE_COMPARE_MISMATCH;

            $shadowRow = MarketplaceOrderShadow::query()
                ->selectRaw(
                    'COUNT(CASE WHEN compare_status = ? THEN 1 END) AS matched,'
                    .' COUNT(CASE WHEN compare_status = ? THEN 1 END) AS mismatch,'
                    .' COUNT(CASE WHEN compare_status = ? THEN 1 END) AS jubelio_missing,'
                    .' COUNT(CASE WHEN compare_status = ? THEN 1 END) AS pending,'
                    .' COUNT(CASE WHEN line_compare_status = ? THEN 1 END) AS line_mismatch',
                    [$matched, $mismatch, $jubelioMissing, $pending, $lineMismatch],
                )
                ->toBase()
                ->first();

            return [
                'matched' => (int) ($shadowRow->matched ?? 0),
                'mismatch' => (int) ($shadowRow->mismatch ?? 0),
                'jubelio_missing' => (int) ($shadowRow->jubelio_missing ?? 0),
                'pending' => (int) ($shadowRow->pending ?? 0),
                'line_mismatch' => (int) ($shadowRow->line_mismatch ?? 0),
                'jubelio_pending_sell' => Jubelioorder::query()
                    ->where('type', 'SELL')
                    ->where('status', 0)
                    ->count(),
            ];
        });
    }
}