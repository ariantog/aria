<?php

namespace App\Console\Commands;

use App\Services\Marketplace\OmnichannelPostSellStatsStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class OmnichannelRefreshPostSellStatsCommand extends Command
{
    protected $signature = 'omnichannel:refresh-post-sell-stats';

    protected $description = 'Rebuild omnichannel_post_sell_stats snapshot from marketplace_channel_orders (reconcile drift).';

    public function handle(OmnichannelPostSellStatsStore $store): int
    {
        if (! Schema::hasTable('omnichannel_post_sell_stats')
            || ! Schema::hasTable('marketplace_channel_orders')) {
            $this->warn('Tabel snapshot atau marketplace_channel_orders belum ada.');

            return self::SUCCESS;
        }

        $row = $store->rebuildFull();
        $this->info(sprintf(
            'Post-sell stats refreshed at %s — posted=%d eligible=%d errors=%d',
            $row->updated_at?->toDateTimeString() ?? 'n/a',
            (int) $row->posted,
            (int) $row->eligible_to_post,
            (int) $row->errors,
        ));

        return self::SUCCESS;
    }
}