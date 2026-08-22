<?php

namespace App\Console\Commands;

use App\Services\ShopeeAds\ShopeeAdsEngineService;
use Illuminate\Console\Command;

class ShopeeAdsProcessCommand extends Command
{
    protected $signature = 'shopee-ads:process';

    protected $description = 'Run Shopee Ads scheduled increments, daily reset, and group replenishment (WIB)';

    public function handle(ShopeeAdsEngineService $engine): int
    {
        if (! config('services.shopee_ads.active')) {
            $this->comment('Shopee Ads integration inactive (SHOPEE_ADS_ACTIVE=false).');

            return self::SUCCESS;
        }

        $schedulesRan = $engine->runDueSchedules();
        $reset = $engine->runDailyResetIfDue();
        $replenish = $engine->runItemReplenishIfDue();

        $this->info("Schedules ran: {$schedulesRan}; daily reset: ".($reset ? 'yes' : 'no').'; replenish: '.($replenish ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
