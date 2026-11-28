<?php

use App\Jobs\PersistShopeePushInboxJob;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Services\Shopee\ShopeePushSignatureVerifier;

beforeEach(function () {
    config([
        'marketplace.webhook.unified_storage' => true,
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.callback_url' => '',
        'services.shopee_push.store_inbox' => true,
        'services.shopee_push.shadow_enabled' => false,
    ]);
});

function postSignedShopeePush(string $body): \Illuminate\Testing\TestResponse
{
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    return test()->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

/**
 * Run one queued persist job (avoids stacked dispatchAfterResponse callbacks in multi-call tests).
 */
function runPersistShopeePushJobFromJson(string $body): void
{
    /** @var array<string, mixed> $payload */
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $callbackUrl = url(route('shopee.push', [], false));

    (new PersistShopeePushInboxJob($payload, true, $callbackUrl))->handle(
        app(\App\Services\Marketplace\ShopeeOrderStatusIngest::class),
        app(MarketplaceWebhookRecorder::class),
        app(MarketplaceWebhookStatusPresenter::class),
        app(\App\Services\Marketplace\ShopeePushWebhookOnOrderApplicator::class),
        app(\App\Services\Marketplace\OmnichannelReturnShopeeListingCorrector::class),
    );
}

it('persists distinct inbox and webhook rows for code 3 status changes and code 30 fulfillment', function () {
    $shopId = 727720655;
    $ordersn = '2611245BWFK367';
    $sharedTimestamp = 1_732_413_400;

    $pushes = [
        '{"data":{"items":[],"ordersn":"'.$ordersn.'","status":"UNPAID","update_time":'.$sharedTimestamp.'},"shop_id":'.$shopId.',"code":3,"timestamp":'.$sharedTimestamp.'}',
        '{"data":{"items":[],"ordersn":"'.$ordersn.'","status":"READY_TO_SHIP","update_time":'.$sharedTimestamp.'},"shop_id":'.$shopId.',"code":3,"timestamp":'.$sharedTimestamp.'}',
        '{"data":{"ordersn":"'.$ordersn.'","package_number":"PKG1","logistics_status":"LOGISTICS_NOT_START"},"shop_id":'.$shopId.',"code":30,"timestamp":'.$sharedTimestamp.'}',
        '{"data":{"ordersn":"'.$ordersn.'","package_number":"PKG1","logistics_status":"LOGISTICS_READY"},"shop_id":'.$shopId.',"code":30,"timestamp":'.($sharedTimestamp + 3).'}',
    ];

    foreach ($pushes as $body) {
        runPersistShopeePushJobFromJson($body);
    }

    expect(ShopeePushInbox::query()->where('ordersn', $ordersn)->count())->toBe(4)
        ->and(MarketplaceWebhookEvent::query()->where('order_ref', $ordersn)->count())->toBe(4);

    $statuses = MarketplaceWebhookEvent::query()
        ->where('order_ref', $ordersn)
        ->orderBy('id')
        ->pluck('order_status')
        ->all();

    expect($statuses)->toBe([
        'UNPAID',
        'READY_TO_SHIP',
        'FULFILLMENT:LOGISTICS_NOT_START',
        'FULFILLMENT:LOGISTICS_READY',
    ]);

    $order = MarketplaceChannelOrder::query()
        ->where('channel', 'shopee')
        ->where('order_ref', $ordersn)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->order_status)->toBe('READY_TO_SHIP');
});

it('records code 30 on unified storage when shopee inbox storage is off', function () {
    config(['services.shopee_push.store_inbox' => false]);

    $body = '{"data":{"ordersn":"FUL-ONLY","logistics_status":"LOGISTICS_READY"},"shop_id":9,"code":30,"timestamp":1660123127}';
    runPersistShopeePushJobFromJson($body);

    expect(ShopeePushInbox::count())->toBe(0)
        ->and(MarketplaceWebhookEvent::query()->where('order_ref', 'FUL-ONLY')->count())->toBe(1);
});

it('stores uncommon shopee push codes in inbox when store_inbox is enabled', function () {
    $body = '{"data":{"ping":true},"shop_id":1,"code":99,"timestamp":12345}';
    postSignedShopeePush($body)->assertOk();

    expect(ShopeePushInbox::query()->where('push_code', 99)->count())->toBe(1)
        ->and(MarketplaceWebhookEvent::query()->where('event_type', '99')->count())->toBe(1);
});

it('dedupes identical shopee pushes by payload hash suffix', function () {
    $body = '{"data":{"ordersn":"DEDUPE1","status":"SHIPPED","update_time":1},"shop_id":1,"code":3,"timestamp":99}';

    postSignedShopeePush($body)->assertOk();
    runPersistShopeePushJobFromJson($body);

    expect(MarketplaceWebhookEvent::query()->where('order_ref', 'DEDUPE1')->count())->toBe(1);
});

it('shopee idempotency keys differ for same timestamp and different ingest status', function () {
    $payloadA = ['code' => 3, 'timestamp' => 100, 'data' => ['status' => 'UNPAID']];
    $payloadB = ['code' => 3, 'timestamp' => 100, 'data' => ['status' => 'READY_TO_SHIP']];

    $keyA = MarketplaceWebhookRecorder::shopeeIdempotencyKey(1, 3, 'SN', $payloadA, 'UNPAID');
    $keyB = MarketplaceWebhookRecorder::shopeeIdempotencyKey(1, 3, 'SN', $payloadB, 'READY_TO_SHIP');

    expect($keyA)->not->toBe($keyB);
});

it('maps shopee code 30 logistics status for ingest', function () {
    $presenter = app(MarketplaceWebhookStatusPresenter::class);

    expect($presenter->resolveIngestStatus('shopee', 30, [
        'code' => 30,
        'data' => ['ordersn' => 'X', 'logistics_status' => 'LOGISTICS_READY'],
    ]))->toBe('FULFILLMENT:LOGISTICS_READY')
        ->and($presenter->eventTypeLabel('shopee', 30))->toBe('Package fulfillment');
});

it('persists code 30 delivery done status in inbox and unified storage', function () {
    $ordersn = 'DELIVERY-DONE-SN';
    $expectedStatus = 'FULFILLMENT:LOGISTICS_DELIVERY_DONE';

    $body = '{"data":{"ordersn":"'.$ordersn.'","package_number":"PKG9","logistics_status":"LOGISTICS_DELIVERY_DONE"},"shop_id":727720655,"code":30,"timestamp":1732413400}';
    runPersistShopeePushJobFromJson($body);

    $inbox = ShopeePushInbox::query()->where('ordersn', $ordersn)->first();
    $event = MarketplaceWebhookEvent::query()->where('order_ref', $ordersn)->first();

    expect($inbox)->not->toBeNull()
        ->and($inbox->order_status)->toBe($expectedStatus)
        ->and($event)->not->toBeNull()
        ->and($event->order_status)->toBe($expectedStatus);
});

it('shows webhook log on shopee shadow detail page', function () {
    $admin = User::factory()->create();

    $ordersn = 'WHLOG-SHOPEE-1';
    $recorder = app(MarketplaceWebhookRecorder::class);
    $recorder->recordShopee(
        ['code' => 3, 'data' => ['ordersn' => $ordersn, 'status' => 'UNPAID']],
        true,
        1,
        3,
        $ordersn,
        'UNPAID',
    );
    $recorder->recordShopee(
        ['code' => 30, 'data' => ['ordersn' => $ordersn, 'logistics_status' => 'LOGISTICS_READY']],
        true,
        1,
        30,
        $ordersn,
        'FULFILLMENT:LOGISTICS_READY',
    );

    \App\Models\MarketplaceOrderShadow::query()->create([
        'channel' => 'shopee',
        'external_order_sn' => $ordersn,
        'shop_id' => 1,
        'channel_status' => 'UNPAID',
        'compare_status' => 'pending',
        'line_compare_status' => 'skipped',
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.shopee.show', $ordersn))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-webhook-log"', false)
        ->assertSee('Package fulfillment', false)
        ->assertSee('Order status', false);
});