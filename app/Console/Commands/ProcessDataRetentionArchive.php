<?php

namespace App\Console\Commands;

use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Console\Command;
use Throwable;

class ProcessDataRetentionArchive extends Command
{
    protected $signature = 'app:process-data-retention-archive';

    protected $description = 'Annual worker: copy the next eligible year to the archive database when not yet archived';

    public function handle(DataRetentionService $retention): int
    {
        if (! $retention->archiveConfigured()) {
            $this->warn('Archive database is not configured — skipping.');

            return self::SUCCESS;
        }

        foreach ($retention->yearsEligibleForArchive() as $year) {
            $run = $retention->runForYear($year);

            if ($run->isArchived()) {
                continue;
            }

            if ($run->status === DataRetentionRun::STATUS_COPYING) {
                $this->warn("Year {$year} is already copying — skipping.");

                return self::SUCCESS;
            }

            try {
                $result = $retention->archiveYear($year);
                $this->info(sprintf(
                    'Archived year %d: %d tx, %d details.',
                    $year,
                    $result['transactions'],
                    $result['details'],
                ));
            } catch (Throwable $e) {
                $this->error("Failed archiving year {$year}: ".$e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->info('No eligible years need archiving.');

        return self::SUCCESS;
    }
}
