<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dashboard counts for native marketplace SELL posting (ops hub + index).
 */
class OmnichannelPostSellDashboardStats
{
    public function __construct(
        private OmnichannelPostSellStatsStore $store,
        private OmnichannelPostSellStatsQuery $statsQuery,
    ) {}

    /**
     * @return array{
     *     posted: int,
     *     eligible_to_post: int,
     *     errors: int,
     *     gate: string,
     *     post_sells_enabled: bool,
     *     post_sell_dry_run: bool,
     *     stats_updated_at?: ?string,
     *     stats_stale?: bool,
     * }
     */
    public function counts(): array
    {
        return $this->store->countsForDashboard();
    }

    /**
     * @return array{posted: int, eligible_to_post: int, errors: int}
     */
    public function computeCountsFromDatabase(): array
    {
        return $this->statsQuery->computeCountsFromDatabase();
    }

    /**
     * @return Builder<MarketplaceChannelOrder>
     */
    public function statsScopeQuery(): Builder
    {
        return $this->statsQuery->statsScopeQuery();
    }
}