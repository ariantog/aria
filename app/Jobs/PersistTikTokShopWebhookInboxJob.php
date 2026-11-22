<?php

namespace App\Jobs;

use App\Models\MarketplaceChannelOrder;
use App\Models\TikTokShopWebhookInbox;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\TikTokShopWebhookOnOrderApplicator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PersistTikTokShopWebhookInboxJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public bool $signatureOk = true,
    ) {}

    public function handle(
        MarketplaceWebhookRecorder $unifiedRecorder,
        TikTokShopWebhookOnOrderApplicator $onOrderApplicator,
    ): void
    {
        if (! config('tiktok_shop.webhook.store_inbox', true)) {
            return;
        }

        if (! Schema::hasTable('tiktok_shop_webhook_inbox')) {
            Log::warning('TikTok Shop webhook inbox table missing — run migration');

            return;
        }

        $notificationId = isset($this->payload['tts_notification_id'])
            ? (string) $this->payload['tts_notification_id']
            : '';

        $rawJson = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        if ($notificationId === '') {
            $notificationId = 'body:'. substr(hash('sha256', $rawJson), 0, 32);
            Log::warning('TikTok Shop webhook missing tts_notification_id — using body hash inbox key', [
                'tts_notification_id' => $notificationId,
            ]);
        }

        $data = is_array($this->payload['data'] ?? null) ? $this->payload['data'] : [];
        $eventType = isset($this->payload['type']) ? (int) $this->payload['type'] : null;
        $shopId = isset($this->payload['shop_id']) ? (string) $this->payload['shop_id'] : null;
        $orderId = isset($data['order_id']) ? (string) $data['order_id'] : null;
        $orderStatus = isset($data['order_status']) ? (string) $data['order_status'] : null;

        try {
            TikTokShopWebhookInbox::query()->firstOrCreate(
                ['tts_notification_id' => $notificationId],
                [
                    'event_type' => $eventType,
                    'shop_id' => $shopId,
                    'order_id' => $orderId,
                    'order_status' => $orderStatus,
                    'signature_ok' => $this->signatureOk,
                    'payload' => $this->payload,
                ],
            );

            $unifiedRecorder->recordTikTok($this->payload, $this->signatureOk);

            $onOrderApplicator->applyFromWebhook($this->payload);

            if (filled($orderId)) {
                $this->dispatchCommercePlatformEnrichIfNeeded($orderId);
            }
        } catch (Throwable $e) {
            Log::error('TikTok Shop webhook inbox persist failed', [
                'tts_notification_id' => $notificationId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function dispatchCommercePlatformEnrichIfNeeded(string $orderId): void
    {
        if (! filter_var(config('tiktok_shop.enrich_commerce_platform', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'commerce_platform')) {
            $existing = MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where('order_ref', $orderId)
                ->value('commerce_platform');

            if (filled($existing)) {
                return;
            }
        }

        EnrichMarketplaceTikTokCommercePlatformJob::dispatch($orderId);
    }
}