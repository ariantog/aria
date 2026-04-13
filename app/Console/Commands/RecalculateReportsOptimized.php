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
                $this->info('Resetting old stats...');
                DB::table('addrbook_stats')->update(['balance' => 0]);
                DB::table('addrbook_dailies')->delete();
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
                    INSERT INTO warehouse_items (warehouse_id, item_id, quantity, created_at, updated_at)
                    SELECT t.receiver_id, td.item_id, SUM(td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    WHERE t.receiver_id IS NOT NULL
                    GROUP BY t.receiver_id, td.item_id
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');
                // Outbound (-)
                DB::statement('
                    INSERT INTO warehouse_items (warehouse_id, item_id, quantity, created_at, updated_at)
                    SELECT t.sender_id, td.item_id, SUM(-td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    WHERE t.sender_id IS NOT NULL
                    GROUP BY t.sender_id, td.item_id
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');

                // 4. Addrbook Balances (addrbook_stats)
                $this->info('Recalculating Addrbook Balances...');

                // Logic Receiver Side (+)
                // Type 1:BUY(na), 2:SELL(-), 9:CASH_IN(+), 10:CASH_OUT(-), 6:TRF(+), 12:ADJ(+), 15:RET(-)
                DB::statement('
                    INSERT INTO addrbook_stats (addrbook_id, balance, created_at, updated_at)
                    SELECT receiver_id, SUM(grand_total), NOW(), NOW()
                    FROM transactions 
                    WHERE receiver_id IS NOT NULL AND type IN (2, 9, 10, 6, 12, 15)
                    GROUP BY receiver_id
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                ');

                // Logic Sender Side (+)
                // Type 1:BUY(+), 9:CASH_IN(+), 10:CASH_OUT(-), 6:TRF(-), 12:ADJ(-), 15:RET(+)
                DB::statement('
                    INSERT INTO addrbook_stats (addrbook_id, balance, created_at, updated_at)
                    SELECT sender_id, 
                           SUM(CASE WHEN type IN (6, 12) THEN -grand_total ELSE grand_total END), 
                           NOW(), NOW()
                    FROM transactions 
                    WHERE sender_id IS NOT NULL AND type IN (1, 9, 10, 6, 12, 15)
                    GROUP BY sender_id
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                ');

                // 5. Daily Reports (addrbook_dailies)
                $this->info('Generating Daily Reports...');
                DB::statement('
                    INSERT INTO addrbook_dailies (addrbook_id, date, buy, sell, `return`, return_supplier, move, transfer, adjust, created_at, updated_at)
                    SELECT 
                        COALESCE(sender_id, receiver_id), 
                        date, 
                        SUM(CASE WHEN type = 1 THEN grand_total ELSE 0 END),
                        SUM(CASE WHEN type = 2 THEN ABS(grand_total) ELSE 0 END),
                        SUM(CASE WHEN type = 15 THEN grand_total ELSE 0 END),
                        SUM(CASE WHEN type = 17 THEN ABS(grand_total) ELSE 0 END),
                        SUM(CASE WHEN type = 3 THEN 1 ELSE 0 END),
                        SUM(CASE WHEN type = 6 THEN grand_total ELSE 0 END),
                        SUM(CASE WHEN type = 12 THEN grand_total ELSE 0 END),
                        NOW(), NOW()
                    FROM transactions
                    GROUP BY COALESCE(sender_id, receiver_id), date
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
