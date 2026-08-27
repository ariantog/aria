<?php

namespace App\Console\Commands;

use App\Models\WarehouseStatBackfill;
use App\Services\WarehouseStatBackfillService;
use Illuminate\Console\Command;

class BackfillWarehouseItemStats extends Command
{
    protected $signature = 'app:backfill-warehouse-item-stats
                            {--months= : Months to rebuild in this batch}
                            {--restart : Start a fresh backfill from the newest month}
                            {--status : Only print the current progress}';

    protected $description = 'Rebuild historical warehouse stats one batch of months at a time';

    public function handle(WarehouseStatBackfillService $backfill): int
    {
        if ($this->option('status')) {
            $this->printStatus($backfill);

            return self::SUCCESS;
        }

        if ($this->option('restart')) {
            $backfill->start();
            $this->info('Backfill restarted from the newest month.');
        }

        $state = $backfill->state();

        if ($state->status === WarehouseStatBackfill::STATUS_IDLE) {
            $this->warn('Backfill has not been started. Run with --restart or start it from the backfill page.');

            return self::SUCCESS;
        }

        if ($state->status === WarehouseStatBackfill::STATUS_COMPLETED) {
            $this->info('Backfill already complete; nothing to do.');

            return self::SUCCESS;
        }

        if (! $state->isRunning()) {
            $this->warn(sprintf('Backfill is %s; not running a batch.', $state->status));

            return self::SUCCESS;
        }

        $months = $this->option('months') !== null ? (int) $this->option('months') : null;
        $result = $backfill->runBatch($months);

        if ($result['months'] === 0) {
            $this->info(sprintf('No months processed (status: %s).', $result['status']));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Rebuilt %d month(s) %s → %s, %d stat row(s) written.',
            $result['months'],
            $result['to'],
            $result['from'],
            $result['rows'],
        ));

        $this->printStatus($backfill);

        return self::SUCCESS;
    }

    private function printStatus(WarehouseStatBackfillService $backfill): void
    {
        $state = $backfill->state();

        $this->line(sprintf(
            'Status: %s | %d/%d month(s) done (%.1f%%) | %d remaining | %d stat row(s) written',
            $state->status,
            $state->months_done,
            $state->months_total,
            $state->progressPercent(),
            $backfill->remainingMonths($state),
            $state->rows_written,
        ));

        if ($state->last_error) {
            $this->error('Last error: '.$state->last_error);
        }
    }
}
