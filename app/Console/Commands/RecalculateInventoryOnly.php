<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecalculateInventoryOnly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'High-performance recalculation of Global Stock and Warehouse Stock only.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Inventory-Only Recalculation...');
        $start = microtime(true);

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () {
                // 1. Reset
                $this->info('Resetting current stock data...');
                DB::table('warehouse_items')->delete();
                DB::table('items')->update(['qty' => 0]);

                // 2. Global Stock (items.qty)
                // Logic: 1:BUY(+), 2:SELL(-), 15:RETURN(+), 17:RET_SUPPLIER(-)
                $this->info('Recalculating Global Stock (items.qty)...');
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

                // 3. Warehouse Stock (warehouse_items)
                $this->info('Recalculating Warehouse Stock...');

                // Process Inbound (+)
                $this->info(' - Processing Inbound items...');
                DB::statement('
                    INSERT INTO warehouse_items (warehouse_id, item_id, quantity, created_at, updated_at)
                    SELECT t.receiver_id, td.item_id, SUM(td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    WHERE t.receiver_id IS NOT NULL
                    GROUP BY t.receiver_id, td.item_id
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');

                // Process Outbound (-)
                $this->info(' - Processing Outbound items...');
                DB::statement('
                    INSERT INTO warehouse_items (warehouse_id, item_id, quantity, created_at, updated_at)
                    SELECT t.sender_id, td.item_id, SUM(-td.quantity), NOW(), NOW()
                    FROM transaction_details td
                    JOIN transactions t ON td.transaction_id = t.id
                    WHERE t.sender_id IS NOT NULL
                    GROUP BY t.sender_id, td.item_id
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ');
            });

            $time = round(microtime(true) - $start, 2);
            $this->info("Inventory recalculation finished in {$time} seconds.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
