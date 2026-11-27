<?php

namespace App\Console\Commands;

use App\Services\TikTokShop\TikTokShopItemBulkLinkService;
use Illuminate\Console\Command;

class ProcessTikTokBulkLinkCommand extends Command
{
    protected $signature = 'app:process-tiktok-bulk-link';

    protected $description = 'Process the next batch (max 1000 rows) for in-progress TikTok Shop bulk link runs';

    public function handle(TikTokShopItemBulkLinkService $service): int
    {
        $processed = $service->processDueRuns();

        if ($processed === 0) {
            $this->line('No TikTok Shop bulk link batch due.');

            return self::SUCCESS;
        }

        $this->info("Processed {$processed} TikTok Shop bulk link batch(es).");

        return self::SUCCESS;
    }
}