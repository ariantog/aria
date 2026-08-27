<?php

namespace App\Console\Commands;

use App\Services\WarehouseItemStatsRebuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateWarehouseItemStats extends Command
{
    protected $signature = 'app:recalculate-warehouse-item-stats
                            {--months= : Only rebuild the last N months instead of the full history}
                            {--since= : Only rebuild from this date onwards (Y-m-d)}
                            {--chunk=500 : Rows per insert statement}';

    protected $description = 'Rebuild per-warehouse per-SKU monthly sell/return statistics';

    public function handle(WarehouseItemStatsRebuilder $rebuilder): int
    {
        DB::connection()->disableQueryLog();

        $bounds = $rebuilder->periodBounds();

        if ($bounds === null) {
            $this->info('No sell or return transaction details found; nothing to recalculate.');

            return self::SUCCESS;
        }

        [$earliest, $rangeEnd] = $bounds;
        $rangeStart = $earliest;
        $isFullRebuild = true;

        if ($since = $this->option('since')) {
            $rangeStart = CarbonImmutable::parse($since)->startOfMonth();
            $isFullRebuild = false;
        } elseif ($months = $this->option('months')) {
            $rangeStart = CarbonImmutable::now()->startOfMonth()->subMonths(max(0, (int) $months - 1));
            $isFullRebuild = false;
        }

        if ($rangeStart->lessThan($earliest)) {
            $rangeStart = $earliest;
        }

        if ($rangeStart->greaterThan($rangeEnd)) {
            $this->info('Nothing to recalculate for the requested window.');

            return self::SUCCESS;
        }

        $monthCount = WarehouseItemStatsRebuilder::periodKey($rangeEnd) - WarehouseItemStatsRebuilder::periodKey($rangeStart) + 1;

        $this->info(sprintf(
            '%s %s → %s (%d month(s)).',
            $isFullRebuild ? 'Full rebuild' : 'Partial rebuild',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $monthCount,
        ));

        if ($isFullRebuild) {
            $rebuilder->purgeOutsideRange($rangeStart, $rangeEnd);
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $started = microtime(true);
        $written = 0;

        $bar = $this->output->createProgressBar($monthCount);
        $bar->start();

        for ($period = $rangeStart; $period->lessThanOrEqualTo($rangeEnd); $period = $period->addMonth()) {
            $written += $rebuilder->rebuildMonth($period, $chunkSize);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf('Done in %.1fs. %d monthly stat row(s) written.', microtime(true) - $started, $written));

        return self::SUCCESS;
    }
}
