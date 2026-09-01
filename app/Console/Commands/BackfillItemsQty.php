<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillItemsQty extends Command
{
    protected $signature = 'app:backfill-items-qty {--chunk=500 : Rows per chunk}';

    protected $description = 'Backfill items.qty from non-deleted physical warehouse stock (virtual excluded).';

    public function handle(): int
    {
        if (! Schema::hasColumn('items', 'qty')) {
            $this->error('items.qty column does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $warehouseTable = Schema::hasTable('warehouse_item')
            ? 'warehouse_item'
            : (Schema::hasTable('warehouse_item') ? 'warehouse_item' : null);

        if (! $warehouseTable) {
            $this->error('No warehouse_item / warehouse_item table found.');

            return self::FAILURE;
        }

        $this->info("Backfilling items.qty from {$warehouseTable} (non-deleted warehouses, virtual excluded)...");

        $physicalType = \App\Models\Addrbook::TYPE_WAREHOUSE;
        $updated = DB::update("
            UPDATE items
            SET qty = COALESCE((
                SELECT SUM(wi.quantity)
                FROM {$warehouseTable} wi
                INNER JOIN customers c ON c.id = wi.warehouse_id
                WHERE wi.item_id = items.id
                  AND c.type = {$physicalType}
                  AND c.deleted_at IS NULL
            ), 0)
        ");

        $this->info("Updated {$updated} item rows.");

        return self::SUCCESS;
    }
}
