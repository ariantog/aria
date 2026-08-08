<?php

namespace App\Console\Commands;

use App\Services\WarehouseArrangementSyncService;
use Illuminate\Console\Command;

class SyncWarehouseArrangement extends Command
{
    protected $signature = 'app:sync-warehouse-arrangement
                            {--destination= : Sync a single arrangement destination warehouse id}
                            {--only=all : destinations, sources, or all}';

    protected $description = 'Pre-compute warehouse arrangement candidates and source matches';

    public function handle(WarehouseArrangementSyncService $sync): int
    {
        $destinationId = $this->option('destination') ? (int) $this->option('destination') : null;
        $only = (string) $this->option('only');

        if (! in_array($only, ['destinations', 'sources', 'all'], true)) {
            $this->error('Invalid --only value. Use destinations, sources, or all.');

            return self::FAILURE;
        }

        if ($only === 'destinations' || $only === 'all') {
            $count = $sync->syncDestinations($destinationId);
            $this->info("Destination sync finished ({$count} candidate row(s) touched).");
        }

        if ($only === 'sources' || $only === 'all') {
            $count = $sync->syncSources($destinationId);
            $this->info("Source sync attached {$count} source row(s).");
        }

        return self::SUCCESS;
    }
}
