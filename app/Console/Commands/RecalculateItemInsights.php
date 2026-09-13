<?php

namespace App\Console\Commands;

use App\Services\ItemInsightSyncService;
use Illuminate\Console\Command;

class RecalculateItemInsights extends Command
{
    protected $signature = 'app:recalculate-item-insights {year : Calendar year} {month : Month 1-12}';

    protected $description = 'Rebuild pre-aggregated item insight rankings for one calendar month';

    public function handle(ItemInsightSyncService $sync): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        try {
            $result = $sync->recalculateMonth($year, $month);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Stored %d rows for %04d-%02d at %s.',
            $result['rows'],
            $result['year'],
            $result['month'],
            $result['calculated_at'],
        ));

        return self::SUCCESS;
    }
}
