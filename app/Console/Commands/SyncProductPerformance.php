<?php

namespace App\Console\Commands;

use App\Services\ProductPerformanceSyncService;
use Illuminate\Console\Command;

class SyncProductPerformance extends Command
{
    protected $signature = 'app:sync-product-performance';

    protected $description = 'Rebuild product performance rollups from warehouse item monthly stats';

    public function handle(ProductPerformanceSyncService $sync): int
    {
        $this->info('Syncing product performance rollups...');
        $result = $sync->syncAll();
        $this->info("Done. {$result['periods']} periods, {$result['rollups']} rollup rows.");

        return self::SUCCESS;
    }
}
