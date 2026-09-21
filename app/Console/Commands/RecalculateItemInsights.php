<?php

namespace App\Console\Commands;

use App\Services\ItemInsightSyncService;
use Illuminate\Console\Command;

class RecalculateItemInsights extends Command
{
    protected $signature = 'app:recalculate-item-insights
                            {year : Calendar year}
                            {month? : Month 1-12 when not using --yearly}
                            {--yearly : Roll up the calendar year}';

    protected $description = 'Rebuild pre-aggregated item insight rankings for one calendar month or year';

    public function handle(ItemInsightSyncService $sync): int
    {
        $year = (int) $this->argument('year');

        if ($this->option('yearly')) {
            return $this->recalculateYear($sync, $year);
        }

        $month = $this->argument('month');
        if ($month === null) {
            $this->error('Provide a month (1-12) or pass --yearly.');

            return self::FAILURE;
        }

        $month = (int) $month;

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

    private function recalculateYear(ItemInsightSyncService $sync, int $year): int
    {
        try {
            $result = $sync->recalculateYear($year);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $months = $result['months_included'] ?? [];
        $monthsLabel = $months !== [] ? ' months '.implode(',', array_map(fn (int $m) => sprintf('%02d', $m), $months)) : '';

        $this->info(sprintf(
            'Stored %d rows for year %04d%s at %s.',
            $result['rows'],
            $result['year'],
            $monthsLabel,
            $result['calculated_at'],
        ));

        return self::SUCCESS;
    }
}
