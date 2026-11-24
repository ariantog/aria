<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MarketplaceWebhookRecorder
{
    public function __construct(
        protected MarketplaceWebhookStatusPresenter $statusPresenter,
        protected MarketplaceChannelOrderWarehousePersist $warehousePersist,
    ) {}

    public const CHANNEL_SHOPEE = 'shopee';

    public const CHANNEL_TIKTOK = 'tiktok';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordTikTok(array $payload, bool $signatureOk = true): void
    {
        if (! $this->unifiedStorageEnabled()) {
            return;
        }

        $notificationId = isset($payload['tts_notification_id'])
            ? (string) $payload['tts_notification_id']
            : '';

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($notificationId === '') {
            $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            $idempotencyKey = self::tikTokBodyIdempotencyKey($raw);
            Log::warning('TikTok webhook missing tts_notification_id — using body hash idempotency', [
                'idempotency_key' => $idempotencyKey,
            ]);
        } else {
            $idempotencyKey = self::tikTokIdempotencyKey($notificationId);
        }

        $this->recordEvent(
            self::CHANNEL_TIKTOK,
            $idempotencyKey,
            [
                'event_type' => isset($payload['type']) ? (string) $payload['type'] : null,
                'shop_ref' => isset($payload['shop_id']) ? (string) $payload['shop_id'] : null,
                'order_ref' => isset($data['order_id']) ? (string) $data['order_id'] : null,
                'order_status' => isset($data['order_status']) ? (string) $data['order_status'] : null,
                'signature_ok' => $signatureOk,
                'payload' => $payload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordShopee(
        array $payload,
        bool $signatureOk,
        ?int $shopId,
        ?int $pushCode,
        ?string $orderRef,
        ?string $orderStatus,
    ): void {
        if (! $this->unifiedStorageEnabled()) {
            return;
        }

        $this->recordEvent(
            self::CHANNEL_SHOPEE,
            self::shopeeIdempotencyKey($shopId, $pushCode, $orderRef, $payload),
            [
                'event_type' => $pushCode !== null ? (string) $pushCode : null,
                'shop_ref' => $shopId !== null ? (string) $shopId : null,
                'order_ref' => filled($orderRef) ? $orderRef : null,
                'order_status' => $orderStatus,
                'signature_ok' => $signatureOk,
                'payload' => $payload,
            ],
        );
    }

    public static function tikTokIdempotencyKey(string $notificationId): string
    {
        return 'tiktok:'. $notificationId;
    }

    public static function tikTokBodyIdempotencyKey(string $rawPayload): string
    {
        return 'tiktok:body:'. substr(hash('sha256', $rawPayload), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function shopeeIdempotencyKey(
        ?int $shopId,
        ?int $pushCode,
        ?string $orderRef,
        array $payload,
    ): string {
        $shop = $shopId ?? 0;
        $code = $pushCode ?? 0;
        $order = filled($orderRef) ? $orderRef : '_';

        $ts = $payload['timestamp'] ?? null;
        if ($ts === null || $ts === '') {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $ts = $data['update_time'] ?? null;
        }

        $suffix = is_numeric($ts) && (string) $ts !== ''
            ? (string) $ts
            : substr(hash('sha256', json_encode($payload) ?: ''), 0, 16);

        return sprintf('shopee:%s:%s:%s:%s', $shop, $code, $order, $suffix);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function recordEvent(string $channel, string $idempotencyKey, array $attributes): void
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            Log::warning('marketplace_webhook_events table missing — run migration');

            return;
        }

        $receivedAt = now();

        $event = MarketplaceWebhookEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            array_merge($attributes, [
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'received_at' => $receivedAt,
            ]),
        );

        if (! $event->wasRecentlyCreated) {
            return;
        }

        if (! filled($event->order_ref)) {
            return;
        }

        if (! Schema::hasTable('marketplace_channel_orders')) {
            Log::warning('marketplace_channel_orders table missing — run migration');

            return;
        }

        $this->upsertChannelOrder($event, $receivedAt);
    }

    /**
     * Import one historical row (backfill). Ignores unified_storage flag.
     *
     * @param  array<string, mixed>  $attributes
     * @return bool true when a new history row was inserted
     */
    public function importHistoricalEvent(
        string $channel,
        string $idempotencyKey,
        array $attributes,
        \DateTimeInterface $receivedAt,
    ): bool {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            return false;
        }

        $event = MarketplaceWebhookEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            array_merge($attributes, [
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'received_at' => $receivedAt,
            ]),
        );

        if (! $event->wasRecentlyCreated) {
            return false;
        }

        if (! filled($event->order_ref)) {
            return true;
        }

        if (! Schema::hasTable('marketplace_channel_orders')) {
            return true;
        }

        $this->upsertChannelOrder($event, $receivedAt);

        return true;
    }

    protected function upsertChannelOrder(MarketplaceWebhookEvent $event, \DateTimeInterface $receivedAt): void
    {
        $order = MarketplaceChannelOrder::query()->firstOrNew([
            'channel' => $event->channel,
            'order_ref' => $event->order_ref,
        ]);

        if (! $order->exists) {
            $order->first_received_at = $receivedAt;
        }

        $order->shop_ref = $event->shop_ref;
        if (! $this->statusPresenter->skipsChannelOrderStatusUpsert($event->order_status)) {
            $order->order_status = $event->order_status;
        }
        $order->last_event_at = $receivedAt;
        $order->last_webhook_event_id = $event->id;
        $order->updated_at = $receivedAt;
        $order->save();

        $this->warehousePersist->persistFromWebhookEvent($order, $event);
    }

    protected function unifiedStorageEnabled(): bool
    {
        return (bool) config('marketplace.webhook.unified_storage', false);
    }
}