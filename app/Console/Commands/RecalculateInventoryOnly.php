<?php

namespace App\Console\Commands;

use App\Services\ItemsQtySyncService;
use Illuminate\Console\Command;

class RecalculateInventoryOnly extends Command
{
    protected $signature = 'inventory:recalculate';

    protected $description = 'Sync items.qty from existing physical warehouse_item rows (does not modify warehouse_item)';

    public function handle(ItemsQtySyncService $sync): int
    {
        $this->info('Syncing items.qty from warehouse_item (physical, non-deleted warehouses only)...');
        $start = microtime(true);

        try {
            $updated = $sync->syncAllFromPhysicalWarehouse();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $time = round(microtime(true) - $start, 2);
        $this->info("Synced {$updated} item row(s) in {$time} seconds.");

        return self::SUCCESS;
    }
}
