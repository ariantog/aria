<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use App\Models\TikTokShopWebhookInbox;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['marketplace.webhook.unified_storage' => true]);
});

it('dedupes tiktok webhook events by idempotency key', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $payload = [
        'type' => 1,
        'tts_notification_id' => 'dedupe-tts-1',
        'shop_id' => '100',
        'data' => [
            'order_id' => 'ORD-TT-1',
            'order_status' => 'UNPAID',
        ],
    ];

    $recorder->recordTikTok($payload, true);
    $recorder->recordTikTok($payload, true);

    expect(MarketplaceWebhookEvent::count())->toBe(1)
        ->and(MarketplaceWebhookEvent::query()->value('idempotency_key'))
        ->toBe('tiktok:dedupe-tts-1');
});

it('upserts marketplace channel order only on first distinct event', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $base = [
        'type' => 1,
        'tts_notification_id' => 'upsert-tts-1',
        'shop_id' => '200',
        'data' => [
            'order_id' => 'ORD-UPSERT',
            'order_status' => 'UNPAID',
        ],
    ];

    $recorder->recordTikTok($base, true);

    $dup = $base;
    $dup['tts_notification_id'] = 'upsert-tts-2';
    $dup['data']['order_status'] = 'AWAITING_SHIPMENT';
    $recorder->recordTikTok($dup, true);

    expect(MarketplaceWebhookEvent::count())->toBe(2);

    $order = MarketplaceChannelOrder::query()
        ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
        ->where('order_ref', 'ORD-UPSERT')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->order_status)->toBe('AWAITING_SHIPMENT')
        ->and($order->shop_ref)->toBe('200');
});

it('prunes webhook events older than retention with dry-run and delete', function () {
    config(['marketplace.webhook.events_retention_days' => 90]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:old-1',
        'event_type' => '1',
        'shop_ref' => null,
        'order_ref' => null,
        'order_status' => null,
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'received_at' => now()->subDays(120),
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:recent-1',
        'event_type' => '1',
        'shop_ref' => null,
        'order_ref' => null,
        'order_status' => null,
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'received_at' => now()->subDays(5),
    ]);

    Artisan::call('marketplace:prune-webhook-events', ['--dry-run' => true]);
    expect(MarketplaceWebhookEvent::count())->toBe(2);

    Artisan::call('marketplace:prune-webhook-events', ['--days' => 90]);
    expect(MarketplaceWebhookEvent::count())->toBe(1)
        ->and(MarketplaceWebhookEvent::query()->value('idempotency_key'))->toBe('tiktok:recent-1');
});

it('prunes legacy shopee and tiktok inbox tables by created_at', function () {
    if (! Schema::hasTable('shopee_push_inbox') || ! Schema::hasTable('tiktok_shop_webhook_inbox')) {
        $this->markTestSkipped('legacy inbox migrations not loaded');
    }

    ShopeePushInbox::query()->create([
        'shop_id' => 1,
        'push_code' => 3,
        'ordersn' => 'OLD-SHOP',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'created_at' => now()->subDays(120),
    ]);
    ShopeePushInbox::query()->create([
        'shop_id' => 1,
        'push_code' => 3,
        'ordersn' => 'NEW-SHOP',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'created_at' => now()->subDays(5),
    ]);

    TikTokShopWebhookInbox::query()->create([
        'tts_notification_id' => 'old-tts',
        'event_type' => 1,
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'created_at' => now()->subDays(120),
    ]);
    TikTokShopWebhookInbox::query()->create([
        'tts_notification_id' => 'new-tts',
        'event_type' => 1,
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'created_at' => now()->subDays(5),
    ]);

    Artisan::call('marketplace:prune-webhook-events', ['--days' => 90]);

    expect(ShopeePushInbox::count())->toBe(1)
        ->and(ShopeePushInbox::query()->value('ordersn'))->toBe('NEW-SHOP')
        ->and(TikTokShopWebhookInbox::count())->toBe(1)
        ->and(TikTokShopWebhookInbox::query()->value('tts_notification_id'))->toBe('new-tts');
});

it('persists tiktok webhook without tts_notification_id using body hash idempotency', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $payload = [
        'type' => 99,
        'shop_id' => '100',
        'data' => ['ping' => true],
    ];
    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $expectedKey = MarketplaceWebhookRecorder::tikTokBodyIdempotencyKey($raw);

    $recorder->recordTikTok($payload, true);
    $recorder->recordTikTok($payload, true);

    expect(MarketplaceWebhookEvent::count())->toBe(1)
        ->and(MarketplaceWebhookEvent::query()->value('idempotency_key'))->toBe($expectedKey)
        ->and(MarketplaceWebhookEvent::query()->value('order_ref'))->toBeNull();
});

it('does not write unified tables when unified storage is disabled', function () {
    config(['marketplace.webhook.unified_storage' => false]);

    app(MarketplaceWebhookRecorder::class)->recordTikTok([
        'tts_notification_id' => 'off-1',
        'data' => ['order_id' => 'X'],
    ]);

    expect(MarketplaceWebhookEvent::count())->toBe(0);
});