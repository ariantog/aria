<?php

namespace App\Console\Commands;

use App\Models\ShopeePushInbox;
use App\Models\TikTokShopWebhookInbox;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillMarketplaceWebhookEventsCommand extends Command
{
    protected $signature = 'marketplace:backfill-webhook-events
                            {--dry-run : Report counts only, do not write}
                            {--limit= : Max legacy rows per channel (default all)}';

    protected $description = 'Backfill marketplace_webhook_events (+ channel orders) from legacy Shopee/TikTok inbox tables';

    public function handle(MarketplaceWebhookRecorder $recorder): int
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            $this->error('Table marketplace_webhook_events does not exist — run PR1 migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? max(1, (int) $limit) : null;

        $stats = [
            'shopee_scanned' => 0,
            'shopee_would_insert' => 0,
            'shopee_inserted' => 0,
            'tiktok_scanned' => 0,
            'tiktok_would_insert' => 0,
            'tiktok_inserted' => 0,
        ];

        if (Schema::hasTable('shopee_push_inbox')) {
            $this->backfillShopee($recorder, $dryRun, $limit, $stats);
        } else {
            $this->warn('shopee_push_inbox missing — skipped Shopee backfill.');
        }

        if (Schema::hasTable('tiktok_shop_webhook_inbox')) {
            $this->backfillTikTok($recorder, $dryRun, $limit, $stats);
        } else {
            $this->warn('tiktok_shop_webhook_inbox missing — skipped TikTok backfill.');
        }

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        if ($dryRun) {
            $this->info('Dry run only — no rows written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $stats
     */
    protected function backfillShopee(
        MarketplaceWebhookRecorder $recorder,
        bool $dryRun,
        ?int $limit,
        array &$stats,
    ): void {
        $query = ShopeePushInbox::query()->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(500, function ($rows) use ($recorder, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                $stats['shopee_scanned']++;
                $payload = is_array($row->payload) ? $row->payload : [];
                $idempotency = MarketplaceWebhookRecorder::shopeeIdempotencyKey(
                    $row->shop_id ? (int) $row->shop_id : null,
                    $row->push_code !== null ? (int) $row->push_code : null,
                    $row->ordersn,
                    $payload,
                );

                if ($dryRun) {
                    if (! \App\Models\MarketplaceWebhookEvent::query()->where('idempotency_key', $idempotency)->exists()) {
                        $stats['shopee_would_insert']++;
                    }

                    continue;
                }

                $inserted = $recorder->importHistoricalEvent(
                    MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
                    $idempotency,
                    [
                        'event_type' => $row->push_code !== null ? (string) $row->push_code : null,
                        'shop_ref' => $row->shop_id ? (string) $row->shop_id : null,
                        'order_ref' => filled($row->ordersn) ? $row->ordersn : null,
                        'order_status' => $row->order_status,
                        'signature_ok' => (bool) $row->signature_ok,
                        'payload' => $payload,
                    ],
                    $row->created_at ?? now(),
                );

                if ($inserted) {
                    $stats['shopee_inserted']++;
                }
            }
        });
    }

    /**
     * @param  array<string, int>  $stats
     */
    protected function backfillTikTok(
        MarketplaceWebhookRecorder $recorder,
        bool $dryRun,
        ?int $limit,
        array &$stats,
    ): void {
        $query = TikTokShopWebhookInbox::query()->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(500, function ($rows) use ($recorder, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                $stats['tiktok_scanned']++;
                $notificationId = (string) $row->tts_notification_id;
                if ($notificationId === '') {
                    continue;
                }

                $idempotency = MarketplaceWebhookRecorder::tikTokIdempotencyKey($notificationId);

                if ($dryRun) {
                    if (! \App\Models\MarketplaceWebhookEvent::query()->where('idempotency_key', $idempotency)->exists()) {
                        $stats['tiktok_would_insert']++;
                    }

                    continue;
                }

                $payload = is_array($row->payload) ? $row->payload : [];

                $inserted = $recorder->importHistoricalEvent(
                    MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
                    $idempotency,
                    [
                        'event_type' => $row->event_type !== null ? (string) $row->event_type : null,
                        'shop_ref' => filled($row->shop_id) ? (string) $row->shop_id : null,
                        'order_ref' => filled($row->order_id) ? $row->order_id : null,
                        'order_status' => $row->order_status,
                        'signature_ok' => (bool) $row->signature_ok,
                        'payload' => $payload,
                    ],
                    $row->created_at ?? now(),
                );

                if ($inserted) {
                    $stats['tiktok_inserted']++;
                }
            }
        });
    }
}