<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWarehouseItemTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-warehouse-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix corrupted or incorrect warehouse_type in warehouse_item table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting fix of warehouse_item.warehouse_type...');

        // 1. Fix literal 'App\Models\Addrbook' or 'AppModelsAddrbook' to '2' (Warehouse)
        // We assume '2' as it's the most common warehouse type.
        // A more robust way would be to join with customers table.

        $affected = DB::table('warehouse_item')
            ->whereIn('warehouse_type', ['App\Models\Addrbook', 'AppModelsAddrbook', 'App\\\\Models\\\\Addrbook'])
            ->update(['warehouse_type' => '2']);

        $this->info("Fixed {$affected} records with literal class names.");

        // 2. More robust fix: Sync with customers.type
        $robustFix = DB::table('warehouse_item')
            ->join('customers', 'warehouse_item.warehouse_id', '=', 'customers.id')
            ->whereColumn('warehouse_item.warehouse_type', '!=', 'customers.type')
            ->update(['warehouse_item.warehouse_type' => DB::raw('customers.type')]);

        $this->info("Synced {$robustFix} records with actual Addrbook types.");

        $this->info('Warehouse types fixed successfully!');

        return Command::SUCCESS;
    }
}
