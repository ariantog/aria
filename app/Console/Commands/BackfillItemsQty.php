<?php

namespace App\Console\Commands;

use App\Services\ItemsQtySyncService;
use Illuminate\Console\Command;

class BackfillItemsQty extends Command
{
    protected $signature = 'app:backfill-items-qty {--chunk=500 : Unused legacy option kept for CLI compatibility}';

    protected $description = 'Backfill items.qty from non-deleted physical warehouse stock (virtual excluded; does not modify warehouse_item)';

    public function handle(ItemsQtySyncService $sync): int
    {
        $this->info('Backfilling items.qty from warehouse_item (non-deleted warehouses, virtual excluded)...');

        try {
            $updated = $sync->syncAllFromPhysicalWarehouse();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Updated {$updated} item rows.");

        return self::SUCCESS;
    }
}
