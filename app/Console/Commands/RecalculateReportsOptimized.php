<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecalculateReportsOptimized extends Command
{
    protected $signature = 'report:recalculate';

    protected $description = 'High-performance recalculation based on TransactionsController logic.';

    public function handle()
    {
        $this->info('Starting Precise Optimized Recalculation...');
        $start = microtime(true);

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () {
                // 1. Reset
                $this->info('Resetting old stock stats...');
                DB::table('warehouse_items')->delete();
                DB::table('items')->update(['qty' => 0]);

                // 2. Global Stock (items.qty)
                // 1:BUY(+), 2:SELL(-), 15:RETURN(+), 17:RET_SUPPLIER(-)
                $this->info('Recalculating Global Stock...');
                DB::statement('
                    UPDATE items i 
                    SET i.qty = (
                        SELECT COALESCE(SUM(
                            CASE 
                                WHEN t.type = 1 THEN td.quantity 
                                WHEN t.type = 2 THEN -td.quantity
                                WHEN t.type = 15 THEN td.quantity
                                WHEN t.type = 17 THEN -td.quantity
                                ELSE 0 
                            END
                        ), 0)
                        FROM transaction_details td
                        JOIN transactions t ON td.transaction_id = t.id
                        WHERE td.item_id = i.id
                    )
                ');

                // 3. Warehouse Stock
                $this->info('Recalculating Warehouse Stock...');
                // Inbound (+)
                DB::statement('
                    INSERT INTO warehouse_items (warehouse_id, item_id, warehouse_type, quantity, created_at, updated_at)
                    SELECT t.receiver_id, td.item_id, a.type, SUM(td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    JOIN addrbooks a ON t.receiver_id = a.id
                    WHERE t.receiver_id IS NOT NULL
                    GROUP BY t.receiver_id, td.item_id, a.type
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');
                // Outbound (-)
                DB::statement('
                    INSERT INTO warehouse_items (warehouse_id, item_id, warehouse_type, quantity, created_at, updated_at)
                    SELECT t.sender_id, td.item_id, a.type, SUM(-td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    JOIN addrbooks a ON t.sender_id = a.id
                    WHERE t.sender_id IS NOT NULL
                    GROUP BY t.sender_id, td.item_id, a.type
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');
            });

            $time = round(microtime(true) - $start, 2);
            $this->info("Recalculation finished in {$time} seconds.");
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
