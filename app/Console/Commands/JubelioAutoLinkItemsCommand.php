<?php

namespace App\Console\Commands;

use App\Services\Jubelio\JubelioItemAutoLinkService;
use Illuminate\Console\Command;

class JubelioAutoLinkItemsCommand extends Command
{
    protected $signature = 'app:jubelio-auto-link-items
                            {--limit= : Max items to process this run (default: service batch size)}';

    protected $description = 'Auto-link Aria SKUs to Jubelio via to-stock search (exact item_code match)';

    public function handle(JubelioItemAutoLinkService $service): int
    {
        $limit = (int) ($this->option('limit') ?: JubelioItemAutoLinkService::DEFAULT_BATCH_PER_RUN);
        $limit = max(1, min($limit, $service->remainingHourlyBudget()));

        $result = $service->processBatch($limit);

        $this->info(sprintf(
            'Jubelio auto-link: %d processed, %d linked, %d API budget remaining this hour.',
            $result['processed'],
            $result['linked'],
            $result['remaining_budget'],
        ));

        return self::SUCCESS;
    }
}
