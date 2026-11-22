<?php

namespace App\Jobs;

use App\Services\Marketplace\MarketplaceTikTokCommercePlatformEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
/**
 * Post-ingest read-only TikTok order detail → commerce_platform (Tokopedia vs TikTok Shop).
 */
class EnrichMarketplaceTikTokCommercePlatformJob implements ShouldQueue
{
    use Queueable;

    /** Avoid endless retries on permanent API failures (403, bad creds). */
    public int $tries = 1;

    public function __construct(
        public string $orderRef,
    ) {}

    public function handle(MarketplaceTikTokCommercePlatformEnricher $enricher): void
    {
        $orderRef = trim($this->orderRef);
        if ($orderRef === '') {
            return;
        }

        if (! $enricher->shouldAttemptEnrichment()) {
            return;
        }

        $enricher->enrichOrder($orderRef);
    }
}