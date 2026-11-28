<?php

namespace App\Observers;

use App\Models\MarketplaceChannelOrder;
use App\Services\Marketplace\OmnichannelDashboardCacheBuster;
use App\Services\Marketplace\OmnichannelPostSellStatsStore;

class MarketplaceChannelOrderObserver
{
    public function __construct(
        private OmnichannelPostSellStatsStore $postSellStats,
        private OmnichannelDashboardCacheBuster $cacheBuster,
    ) {}

    public function saved(MarketplaceChannelOrder $order): void
    {
        $original = $order->getOriginal();
        $this->postSellStats->recordOrderChange($order, $order->wasRecentlyCreated ? null : $original);
        $this->cacheBuster->bumpOpsBucketVersion();
    }

    public function deleted(MarketplaceChannelOrder $order): void
    {
        $this->postSellStats->recordOrderRemoved($order->getAttributes());
        $this->cacheBuster->bumpOpsBucketVersion();
    }
}