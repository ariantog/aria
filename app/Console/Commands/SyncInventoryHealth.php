<?php

namespace App\Console\Commands;

use App\Services\InventoryHealth\InventoryHealthSyncService;
use Illuminate\Console\Command;

class SyncInventoryHealth extends Command
{
    protected $signature = 'app:sync-inventory-health';

    protected $description = 'Rebuild Inventory Health snapshots from warehouse item monthly stats and current stock';

    public function handle(InventoryHealthSyncService $sync): int
    {
        $this->info('Syncing inventory health snapshots...');
        $result = $sync->syncAll();
        $this->info("Done. {$result['warehouses']} warehouses, {$result['rows']} snapshot rows.");

        return self::SUCCESS;
    }
}
