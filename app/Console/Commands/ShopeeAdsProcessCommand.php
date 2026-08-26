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
        // Automation on/off is controlled by Cron Manager (scheduled_tasks), not a separate .env flag.

        $schedulesRan = $engine->runDueSchedules();
        $reset = $engine->runDailyResetIfDue();
        $replenish = $engine->runItemReplenishIfDue();

        $this->info("Schedules ran: {$schedulesRan}; daily reset: ".($reset ? 'yes' : 'no').'; replenish: '.($replenish ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
