<?php

namespace App\Jobs;

use App\Services\Marketplace\OmnichannelPostSellStatsStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;

class RefreshOmnichannelPostSellStatsJob implements ShouldQueue
{
    use Queueable;

    public function handle(OmnichannelPostSellStatsStore $store): void
    {
        if (! Schema::hasTable('omnichannel_post_sell_stats')
            || ! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        $store->rebuildFull();
    }
}