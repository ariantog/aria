<?php

use App\Jobs\EnrichMarketplaceTikTokCommercePlatformJob;
use App\Jobs\PersistTikTokShopWebhookInboxJob;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Services\Marketplace\MarketplaceTikTokCommercePlatformEnricher;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config([
        'tiktok_shop.app_key' => 'abcdef',
        'tiktok_shop.app_secret' => '123',
        'tiktok_shop.webhook.enabled' => true,
        'tiktok_shop.webhook.verify_signature' => true,
        'tiktok_shop.webhook.store_inbox' => true,
        'tiktok_shop.driver' => 'mock',
        'tiktok_shop.enrich_commerce_platform' => true,
        'marketplace.webhook.unified_storage' => true,
    ]);
});

it('dispatches commerce_platform enrich job when tiktok webhook has order_id', function () {
    Bus::fake([EnrichMarketplaceTikTokCommercePlatformJob::class]);

    $body = '{"type":1,"tts_notification_id":"enrich-dispatch-1","shop_id":"7495540735365777507","timestamp":1718305585,"data":{"order_id":"TOKO-WEBHOOK-1","order_status":"UNPAID","update_time":1718305585}}';
    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertOk();

    Bus::assertDispatched(EnrichMarketplaceTikTokCommercePlatformJob::class, function (EnrichMarketplaceTikTokCommercePlatformJob $job) {
        return $job->orderRef === 'TOKO-WEBHOOK-1';
    });
});

it('enriches tokopedia commerce_platform from mock driver via job', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
        'order_ref' => 'TOKO-JOB-1',
        'shop_ref' => 'shop-1',
        'order_status' => 'UNPAID',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:enrich-job-1',
        'event_type' => '1',
        'shop_ref' => 'shop-1',
        'order_ref' => 'TOKO-JOB-1',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'received_at' => now(),
    ]);

    (new EnrichMarketplaceTikTokCommercePlatformJob('TOKO-JOB-1'))->handle(app(MarketplaceTikTokCommercePlatformEnricher::class));

    expect(MarketplaceChannelOrder::query()->where('order_ref', 'TOKO-JOB-1')->value('commerce_platform'))
        ->toBe('TOKOPEDIA')
        ->and(MarketplaceWebhookEvent::query()->where('order_ref', 'TOKO-JOB-1')->value('commerce_platform'))
        ->toBe('TOKOPEDIA');
});

it('backfills commerce_platform via artisan command', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'TOKO-CMD-77',
        'shop_ref' => 'shop-cmd',
        'order_status' => 'SHIPPED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Artisan::call('marketplace:enrich-tiktok-commerce-platform', ['--order' => 'TOKO-CMD-77']);

    expect(MarketplaceChannelOrder::query()->where('order_ref', 'TOKO-CMD-77')->value('commerce_platform'))
        ->toBe('TOKOPEDIA');
});

it('backfills commerce_platform on new webhook when channel order already has platform', function () {
    Bus::fake([EnrichMarketplaceTikTokCommercePlatformJob::class]);

    $orderRef = 'LATE-WEBHOOK-99';
    MarketplaceChannelOrder::query()->create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
        'order_ref' => $orderRef,
        'shop_ref' => '7494083630891238919',
        'order_status' => 'AWAITING_SHIPMENT',
        'commerce_platform' => 'TIKTOK_SHOP',
        'last_event_at' => now()->subMinutes(20),
        'first_received_at' => now()->subMinutes(20),
    ]);

    $job = new PersistTikTokShopWebhookInboxJob([
        'type' => 1,
        'tts_notification_id' => 'late-status-7631',
        'shop_id' => '7494083630891238919',
        'data' => [
            'order_id' => $orderRef,
            'order_status' => 'AWAITING_COLLECTION',
        ],
    ], true);

    $job->handle(
        app(\App\Services\Marketplace\MarketplaceWebhookRecorder::class),
        app(\App\Services\Marketplace\TikTokShopWebhookOnOrderApplicator::class),
    );

    Bus::assertNotDispatched(EnrichMarketplaceTikTokCommercePlatformJob::class);

    expect(MarketplaceWebhookEvent::query()
        ->where('order_ref', $orderRef)
        ->where('idempotency_key', 'tiktok:late-status-7631')
        ->value('commerce_platform'))
        ->toBe('TIKTOK_SHOP');
});

it('does not dispatch enrich job when feature flag is off', function () {
    config(['tiktok_shop.enrich_commerce_platform' => false]);
    Bus::fake([EnrichMarketplaceTikTokCommercePlatformJob::class]);

    $body = '{"type":1,"tts_notification_id":"enrich-off-1","shop_id":"7495540735365777507","timestamp":1718305585,"data":{"order_id":"OFF-1","order_status":"UNPAID"}}';
    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertOk();

    Bus::assertNotDispatched(EnrichMarketplaceTikTokCommercePlatformJob::class);
});

it('persists platform_warehouse_id from mock get order detail via enrich job', function () {
    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    MarketplaceChannelOrder::query()->create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
        'order_ref' => 'WH-ENRICH-1',
        'shop_ref' => $shopRef,
        'order_status' => 'UNPAID',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    (new EnrichMarketplaceTikTokCommercePlatformJob('WH-ENRICH-1'))
        ->handle(app(MarketplaceTikTokCommercePlatformEnricher::class));

    expect(MarketplaceChannelOrder::query()->where('order_ref', 'WH-ENRICH-1')->value('platform_warehouse_id'))
        ->toBe($platformWhId);
});