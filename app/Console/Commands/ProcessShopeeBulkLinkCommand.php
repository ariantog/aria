<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeItemBulkLinkService;
use Illuminate\Console\Command;

class ProcessShopeeBulkLinkCommand extends Command
{
    protected $signature = 'app:process-shopee-bulk-link';

    protected $description = 'Process the next batch (max 1000 rows) for in-progress Shopee bulk link runs';

    public function handle(ShopeeItemBulkLinkService $service): int
    {
        $processed = $service->processDueRuns();

        if ($processed === 0) {
            $this->line('No Shopee bulk link batch due.');

            return self::SUCCESS;
        }

        $this->info("Processed {$processed} Shopee bulk link batch(es).");

        return self::SUCCESS;
    }
}
