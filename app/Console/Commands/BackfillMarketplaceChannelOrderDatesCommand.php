<?php

namespace App\Console\Commands;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Services\Marketplace\MarketplaceChannelOrderOrderedAtResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillMarketplaceChannelOrderDatesCommand extends Command
{
    protected $signature = 'marketplace:backfill-channel-order-dates
                            {--dry-run : Report only, do not write}
                            {--limit= : Max orders to process}';

    protected $description = 'Fill marketplace_ordered_at from stored webhook payloads (Shopee order_sn / payload timestamps)';

    public function handle(MarketplaceChannelOrderOrderedAtResolver $resolver): int
    {
        if (! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'marketplace_ordered_at')) {
            $this->error('Column marketplace_ordered_at missing — run migration first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? max(1, (int) $limit) : null;

        $query = MarketplaceChannelOrder::query()->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        $scanned = 0;
        $updated = 0;

        $query->chunkById(200, function ($orders) use ($resolver, $dryRun, &$scanned, &$updated): void {
            foreach ($orders as $order) {
                $scanned++;
                $events = MarketplaceWebhookEvent::query()
                    ->where('channel', $order->channel)
                    ->where('order_ref', $order->order_ref)
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->get(['payload', 'order_status']);

                $best = $order->marketplace_ordered_at;
                foreach ($events as $event) {
                    $payload = is_array($event->payload) ? $event->payload : [];
                    $candidate = $resolver->resolveFromWebhookPayload(
                        (string) $order->channel,
                        (string) $order->order_ref,
                        $payload,
                        $event->order_status,
                    );
                    if ($candidate === null) {
                        continue;
                    }

                    if ($best === null || $candidate->lt($best)) {
                        $best = $candidate;
                    }
                }

                if ($best === null) {
                    $fallback = $resolver->parseShopeeOrderSnDate((string) $order->order_ref);
                    if ($fallback !== null) {
                        $best = $fallback;
                    }
                }

                if ($best === null) {
                    continue;
                }

                $current = $order->marketplace_ordered_at;
                if ($current !== null && $current->lte($best)) {
                    continue;
                }

                if (! $dryRun) {
                    $order->marketplace_ordered_at = $best;
                    $order->save();
                }
                $updated++;
            }
        });

        $this->info("Scanned {$scanned} order(s); ".($dryRun ? 'would update' : 'updated')." {$updated}.");

        return self::SUCCESS;
    }
}