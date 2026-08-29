<?php

namespace App\Console\Commands;

use App\Services\Reporting\BalanceAsOfService;
use App\Services\Reporting\ReportingPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotReportingBalancesCommand extends Command
{
    protected $signature = 'reporting:snapshot-balances
                            {--date= : Single as-of date (Y-m-d)}
                            {--from= : First month Y-m (snapshots each month-end)}
                            {--to= : Last month Y-m}
                            {--force : Recompute even when a snapshot already exists}';

    protected $description = 'Persist month-end addrbook balances from transaction running balances (not current stats)';

    public function handle(BalanceAsOfService $balances): int
    {
        $dates = $this->resolveDates();
        if ($dates === []) {
            $this->error('No dates to snapshot.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        foreach ($dates as $date) {
            $asOf = Carbon::parse($date)->startOfDay();
            $existed = $balances->hasSnapshot($asOf->toDateString());

            if ($existed && ! $force) {
                $this->line("  {$asOf->toDateString()}  skipped (snapshot exists)");

                continue;
            }

            $rows = $balances->balancesAsOf($asOf, persist: true, refresh: $force || $existed);
            $this->info(sprintf('  %s  %d contacts (%s)', $asOf->toDateString(), $rows->count(), $existed ? 'replaced' : 'created'));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveDates(): array
    {
        if ($date = $this->option('date')) {
            return [Carbon::parse($date)->toDateString()];
        }

        if ($this->option('from') || $this->option('to')) {
            $from = Carbon::parse($this->option('from') ?: '2026-01')->startOfMonth();
            $to = Carbon::parse($this->option('to') ?: now()->format('Y-m'))->startOfMonth();
            $dates = [];
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $dates[] = ReportingPeriod::monthEnd($cursor->year, $cursor->month)->toDateString();
                $cursor->addMonth();
            }

            return $dates;
        }

        $previous = now()->copy()->startOfMonth()->subDay();

        return [$previous->toDateString()];
    }
}
