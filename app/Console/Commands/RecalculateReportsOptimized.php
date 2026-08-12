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
        $this->info('Starting Precise Recalculation from Legacy...');
        $start = microtime(true);

        $legacyDb = DB::connection('core_legacy');

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($legacyDb) {
                // 1. Reset
                $this->info('Clearing warehouse_item table (allowed for recalculation)...');
                DB::table('warehouse_item')->delete();

                $this->info('Resetting items quantities to zero (no rows deleted)...');
                DB::table('items')->update(['qty' => 0]);

                // 2. Load existing local references for note generation
                $this->info('Loading local references...');
                $existingItemIds = DB::table('items')->pluck('id')->flip()->toArray();
                $existingAddrbooks = DB::table('customers')->get(['id', 'type'])->keyBy('id');

                // 3. Fetch and Batch Insert from Legacy
                $this->info('Fetching data from legacy and syncing...');

                $total = $legacyDb->table('warehouse_item')->count();
                $bar = $this->output->createProgressBar($total);
                $bar->start();

                $batchSize = 1000;
                $insertBatch = [];

                $legacyDb->table('warehouse_item as wi')
                    ->leftJoin('customers as c', 'wi.warehouse_id', '=', 'c.id')
                    ->select('wi.item_id', 'wi.warehouse_id', 'wi.quantity', 'c.type as legacy_type')
                    ->orderBy('wi.id')
                    ->chunk($batchSize, function ($records) use (&$insertBatch, $existingItemIds, $existingAddrbooks, $bar) {
                        foreach ($records as $record) {
                            $notes = [];
                            $isItemFound = isset($existingItemIds[$record->item_id]);
                            $localAddrbook = $existingAddrbooks->get($record->warehouse_id);
                            $isWarehouseFound = ! is_null($localAddrbook);

                            if (! $isItemFound) {
                                $notes[] = 'Item not found in current project';
                            }
                            if (! $isWarehouseFound) {
                                $notes[] = 'Warehouse/Addrbook not found in current project';
                            }

                            $note = implode(', ', $notes);
                            $warehouseType = $isWarehouseFound ? $localAddrbook->type : ($record->legacy_type ?? 99);

                            $insertBatch[] = [
                                'item_id' => $record->item_id,
                                'warehouse_id' => $record->warehouse_id,
                                'warehouse_type' => $warehouseType,
                                'quantity' => $record->quantity,
                                'note' => $note ?: null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $bar->advance();
                        }

                        if (count($insertBatch) >= 1000) {
                            DB::table('warehouse_item')->insert($insertBatch);
                            $insertBatch = [];
                        }
                    });

                // Final batch
                if (! empty($insertBatch)) {
                    DB::table('warehouse_item')->insert($insertBatch);
                }

                $bar->finish();
                $this->newLine();

                // 4. Update Global Stock (items.qty) based on synced warehouse_item
                $this->info('Updating Global Stock in items table...');
                DB::statement('
                    UPDATE items i 
                    SET i.qty = (
                        SELECT COALESCE(SUM(quantity), 0)
                        FROM warehouse_item wi
                        WHERE wi.item_id = i.id
                    )
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
