<?php

namespace App\Services;

use App\Models\Addrbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemsQtySyncService
{
    /**
     * Sync items.qty from non-deleted physical warehouse_item rows (virtual excluded).
     * Reads warehouse_item only — never writes to it.
     */
    public function syncAllFromPhysicalWarehouse(): int
    {
        if (! Schema::hasColumn('items', 'qty')) {
            throw new \RuntimeException('items.qty column does not exist. Run migrations first.');
        }

        if (! Schema::hasTable('warehouse_item')) {
            throw new \RuntimeException('warehouse_item table does not exist.');
        }

        $physicalType = Addrbook::TYPE_WAREHOUSE;

        return DB::update("
            UPDATE items
            SET qty = COALESCE((
                SELECT SUM(wi.quantity)
                FROM warehouse_item wi
                INNER JOIN customers c ON c.id = wi.warehouse_id
                WHERE wi.item_id = items.id
                  AND c.type = {$physicalType}
                  AND c.deleted_at IS NULL
            ), 0)
        ");
    }
}
