<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\DailyInventorySummary;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecalculateInventoryHealth extends Command
{
    protected $signature = 'app:recalculate-inventory-health';

    protected $description = 'Recalculate daily inventory summaries (Sale Qty & Stock for TYPE_WAREHOUSE only)';

    public function handle()
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $this->info('Starting High Performance recalculation of Daily Sale Summaries...');
        $start = microtime(true);

        Schema::disableForeignKeyConstraints();

        try {
            $this->info('Resetting summary table...');
            DailyInventorySummary::truncate();

            $typeSell = Transaction::TYPE_SELL;
            $whType = Addrbook::TYPE_WAREHOUSE;

            // 1. Proses hanya transaksi SELL di mana SENDER adalah WAREHOUSE
            $this->info("Processing sales for warehouses only...");

            $years = DB::table('transactions')->selectRaw('DISTINCT YEAR(date) as year')->pluck('year');

            foreach ($years as $year) {
                $sql = "
                    INSERT INTO daily_inventory_summaries 
                    (date, warehouse_id, item_id, qty_sell, created_at, updated_at)
                    SELECT 
                        t.date,
                        t.sender_id,
                        td.item_id,
                        SUM(td.quantity),
                        NOW(),
                        NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    JOIN items i ON td.item_id = i.id
                    JOIN addrbooks a ON t.sender_id = a.id
                    WHERE t.type = $typeSell 
                      AND a.type = $whType 
                      AND YEAR(t.date) = $year
                    GROUP BY t.date, t.sender_id, td.item_id
                ";

                DB::statement($sql);
                $this->info("Year $year sales processed.");
            }

            // 2. Snapshot stok hanya untuk WAREHOUSE
            $this->info('Recording current stock snapshots for warehouses only...');
            $this->snapshotStockOptimized();

            $time = round(microtime(true) - $start, 2);
            $this->info("Recalculation completed successfully in {$time} seconds.");

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    protected function snapshotStockOptimized()
    {
        $whType = Addrbook::TYPE_WAREHOUSE;
        
        $sql = "
            INSERT INTO daily_inventory_summaries 
            (date, warehouse_id, item_id, stock_on_hand, created_at, updated_at)
            SELECT CURDATE(), wi.warehouse_id, wi.item_id, wi.quantity, NOW(), NOW()
            FROM warehouse_items wi
            JOIN addrbooks a ON wi.warehouse_id = a.id
            WHERE a.type = $whType
            ON DUPLICATE KEY UPDATE 
                stock_on_hand = VALUES(stock_on_hand),
                updated_at = VALUES(updated_at)
        ";

        DB::statement($sql);
    }
}
