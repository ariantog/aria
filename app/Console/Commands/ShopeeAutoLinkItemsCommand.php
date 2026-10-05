<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeItemAutoLinkService;
use Illuminate\Console\Command;

class ShopeeAutoLinkItemsCommand extends Command
{
    protected $signature = 'app:shopee-auto-link-items
                            {--limit= : Max items to process this run}';

    protected $description = 'Auto-link Aria SKUs to Shopee products when item_sku / model_sku matches exactly';

    public function handle(ShopeeItemAutoLinkService $service): int
    {
        $limit = (int) ($this->option('limit') ?: ShopeeItemAutoLinkService::DEFAULT_BATCH_PER_RUN);
        $result = $service->processBatch($limit);

        $this->info(sprintf(
            'Shopee auto-link: %d processed, %d linked, %d API budget remaining this hour.',
            $result['processed'],
            $result['linked'],
            $result['remaining_budget'],
        ));

        return self::SUCCESS;
    }
}
