<?php

namespace App\Console\Commands;

use App\Services\ShopeeAds\ShopeeAdsEngineService;
use Illuminate\Console\Command;

class ShopeeAdsProcessCommand extends Command
{
    protected $signature = 'shopee-ads:process {--explain : Print full diagnostics even when jobs ran}';

    protected $description = 'Run Shopee Ads scheduled increments, daily reset, and group replenishment (WIB)';

    public function handle(ShopeeAdsEngineService $engine): int
    {
        // Automation on/off is controlled by Cron Manager (scheduled_tasks), not a separate .env flag.

        $schedulesRan = $engine->runDueSchedules();
        $reset = $engine->runDailyResetIfDue();
        $replenish = $engine->runItemReplenishIfDue();

        $this->info("Schedules ran: {$schedulesRan}; daily reset: ".($reset ? 'yes' : 'no').'; replenish: '.($replenish ? 'yes' : 'no'));

        $nothingRan = $schedulesRan === 0 && ! $reset && ! $replenish;

        if ($nothingRan || $this->option('explain')) {
            $this->printDiagnostics($engine->getRunDiagnostics());
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     now_wib: string,
     *     now_utc: string,
     *     current_slot: string,
     *     automation_timezone: string,
     *     app_timezone: string,
     *     php_timezone: string,
     *     paused: bool,
     *     authorized: bool,
     *     schedules: list<string>,
     *     daily_reset: list<string>,
     *     replenish: list<string>,
     * }  $diag
     */
    private function printDiagnostics(array $diag): void
    {
        $this->newLine();
        $this->comment('Diagnostics');
        $this->line('  WIB (GMT+7): '.$diag['now_wib'].' — slot '.$diag['current_slot']);
        $this->line('  UTC: '.$diag['now_utc'].' | PHP tz: '.$diag['php_timezone'].' | app: '.$diag['app_timezone'].' | automation: '.$diag['automation_timezone']);
        $this->line('  Status: '.($diag['paused'] ? 'PAUSED' : 'active').'; OAuth: '.($diag['authorized'] ? 'ok' : 'missing'));

        $this->line('  Schedules:');
        foreach ($diag['schedules'] as $note) {
            $this->line('    • '.$note);
        }
        if ($diag['schedules'] === []) {
            $this->line('    • (slot cocok — increment akan jalan di menit ini)');
        }

        $this->line('  Daily reset:');
        foreach ($diag['daily_reset'] as $note) {
            $this->line('    • '.$note);
        }
        if ($diag['daily_reset'] === []) {
            $this->line('    • (due now)');
        }

        $this->line('  Replenish:');
        foreach ($diag['replenish'] as $note) {
            $this->line('    • '.$note);
        }
        if ($diag['replenish'] === []) {
            $this->line('    • (due now)');
        }

        $this->newLine();
        $this->comment('Increment jalan hanya di menit jadwal (HH:MM). Uji manual: Daily Reset / Replenish / Boost di /shopee-ads.');
    }
}
