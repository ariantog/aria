<?php

namespace App\Console\Commands;

use App\Models\MarketplaceChannelOrder;
use App\Services\Marketplace\MarketplaceTikTokCommercePlatformEnricher;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class EnrichTikTokCommercePlatformCommand extends Command
{
    protected $signature = 'marketplace:enrich-tiktok-commerce-platform
                            {--order= : Single TikTok order_ref}
                            {--limit= : Max rows to process (default all matching)}
                            {--since= : Only channel orders with last_event_at on/after (Y-m-d)}';

    protected $description = 'Backfill commerce_platform on TikTok marketplace rows via read-only Get Order Detail';

    public function handle(MarketplaceTikTokCommercePlatformEnricher $enricher): int
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            $this->error('marketplace_channel_orders missing — run migrations first.');

            return self::FAILURE;
        }

        if (! $enricher->shouldAttemptEnrichment()) {
            $this->warn('Commerce platform enrich is disabled or credentials are missing.');

            return self::SUCCESS;
        }

        $orderRef = $this->option('order');
        if (filled($orderRef)) {
            $platform = $enricher->enrichOrder((string) $orderRef);
            $this->info($platform !== null
                ? "Enriched {$orderRef} → {$platform}"
                : "No commerce_platform resolved for {$orderRef}");

            return self::SUCCESS;
        }

        $query = MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
            ->where(function ($q): void {
                $q->whereNull('commerce_platform')
                    ->orWhere('commerce_platform', '');
            })
            ->orderBy('id');

        $since = $this->option('since');
        if (filled($since)) {
            try {
                $query->where('last_event_at', '>=', Carbon::parse((string) $since)->startOfDay());
            } catch (\Throwable $e) {
                $this->error('Invalid --since date: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $limit = $this->option('limit');
        $max = $limit !== null && $limit !== '' ? max(1, (int) $limit) : null;
        if ($max !== null) {
            $query->limit($max);
        }

        $enriched = 0;
        $skipped = 0;
        $rows = $query->get(['order_ref']);

        foreach ($rows as $row) {
            $ref = (string) $row->order_ref;
            if ($ref === '') {
                $skipped++;

                continue;
            }

            $platform = $enricher->enrichOrder($ref);
            if (filled($platform)) {
                $enriched++;
                $this->line("{$ref} → {$platform}");
            } else {
                $skipped++;
            }
        }

        $this->info("Done — enriched: {$enriched}, no platform: {$skipped}");

        return self::SUCCESS;
    }
}