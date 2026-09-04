<?php

namespace App\Console\Commands;

use App\Services\ItemsQtySyncService;
use Illuminate\Console\Command;

class ResetReports extends Command
{
    protected $signature = 'app:reset-reports {--force : Skip confirmation}';

    protected $description = 'Resync items.qty from physical warehouse_item rows (does not modify warehouse_item)';

    public function handle(ItemsQtySyncService $sync): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'Resync items.qty from existing physical warehouse stock? warehouse_item rows will not be changed.',
        )) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->info('Syncing items.qty from warehouse_item...');

        try {
            $updated = $sync->syncAllFromPhysicalWarehouse();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Resynced {$updated} item row(s). warehouse_item was not modified.");

        return self::SUCCESS;
    }
}
