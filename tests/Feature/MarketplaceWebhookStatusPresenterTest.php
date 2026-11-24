<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['marketplace.webhook.unified_storage' => true]);
});

it('maps shopee order status push ingest to data status', function () {
    $presenter = app(MarketplaceWebhookStatusPresenter::class);

    $status = $presenter->resolveIngestStatus('shopee', 3, [
        'code' => 3,
        'data' => [
            'ordersn' => 'SN1',
            'status' => 'READY_TO_SHIP',
        ],
    ]);

    expect($status)->toBe('READY_TO_SHIP')
        ->and($presenter->eventTypeLabel('shopee', 3))->toBe('Order status')
        ->and($presenter->normalizeBucket('shopee', null, 'READY_TO_SHIP'))->toBe('awaiting_shipment')
        ->and($presenter->displayStatus('shopee', 'READY_TO_SHIP'))->toBe('READY TO SHIP');
});

it('does not let fulfillment ingest clobber channel order summary status', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $orderRef = 'FUL-NO-CLOBBER';

    $recorder->recordShopee(
        ['code' => 3, 'data' => ['ordersn' => $orderRef, 'status' => 'READY_TO_SHIP']],
        true,
        1,
        3,
        $orderRef,
        'READY_TO_SHIP',
    );

    $recorder->recordShopee(
        ['code' => 30, 'data' => ['ordersn' => $orderRef, 'logistics_status' => 'LOGISTICS_READY']],
        true,
        1,
        30,
        $orderRef,
        'FULFILLMENT:LOGISTICS_READY',
    );

    $order = MarketplaceChannelOrder::query()
        ->where('order_ref', $orderRef)
        ->first();

    expect($order->order_status)->toBe('READY_TO_SHIP');
});

it('does not downgrade channel order status on older unpaid replay', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $orderRef = 'RANK-DOWNGRADE';

    $recorder->recordShopee(
        ['code' => 3, 'data' => ['ordersn' => $orderRef, 'status' => 'READY_TO_SHIP'], 'timestamp' => 2],
        true,
        1,
        3,
        $orderRef,
        'READY_TO_SHIP',
    );

    $recorder->recordShopee(
        ['code' => 3, 'data' => ['ordersn' => $orderRef, 'status' => 'UNPAID'], 'timestamp' => 1],
        true,
        1,
        3,
        $orderRef,
        'UNPAID',
    );

    expect(MarketplaceChannelOrder::query()->where('order_ref', $orderRef)->value('order_status'))
        ->toBe('READY_TO_SHIP');
});

it('stores processed summary for shopee cod unpaid webhook', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $orderRef = 'COD-UNPAID-1';

    $recorder->recordShopee(
        [
            'code' => 3,
            'data' => [
                'ordersn' => $orderRef,
                'status' => 'UNPAID',
                'payment_method' => 'COD',
            ],
        ],
        true,
        1,
        3,
        $orderRef,
        'UNPAID',
    );

    expect(MarketplaceChannelOrder::query()->where('order_ref', $orderRef)->value('order_status'))
        ->toBe('PROCESSED');
});

it('maps shopee tracking push to tracking prefix ingest status', function () {
    $presenter = app(MarketplaceWebhookStatusPresenter::class);

    $status = $presenter->resolveIngestStatus('shopee', 4, [
        'code' => 4,
        'data' => [
            'ordersn' => 'SN-TRK',
            'tracking_no' => 'SPX-998877',
            'package_number' => 'PKG1',
        ],
    ]);

    expect($status)->toBe('tracking:SPX-998877')
        ->and($presenter->eventTypeLabel('shopee', 4))->toBe('Tracking')
        ->and($presenter->displayStatus('shopee', $status))->toBe('TRACKING · SPX-998877')
        ->and($presenter->normalizeBucket('shopee', null, $status))->toBe('tracking_update')
        ->and($presenter->skipsChannelOrderStatusUpsert($status))->toBeTrue();
});

it('maps tiktok unpaid and awaiting shipment buckets', function () {
    $presenter = app(MarketplaceWebhookStatusPresenter::class);

    expect($presenter->resolveIngestStatus('tiktok', 1, [
        'type' => 1,
        'data' => ['order_id' => 'TT1', 'order_status' => 'UNPAID'],
    ]))->toBe('UNPAID')
        ->and($presenter->normalizeBucket('tiktok', null, 'UNPAID'))->toBe('unpaid')
        ->and($presenter->normalizeBucket('tiktok', 'TOKOPEDIA', 'AWAITING_SHIPMENT'))->toBe('awaiting_shipment')
        ->and($presenter->eventTypeLabel('tiktok', 1))->toBe('Order status');
});

it('stores tracking on unified event but does not clobber channel order status', function () {
    $recorder = app(MarketplaceWebhookRecorder::class);
    $shopId = 42;
    $orderRef = 'ORD-NO-CLOBBER';

    $statusPayload = [
        'code' => 3,
        'shop_id' => $shopId,
        'timestamp' => 1_700_000_000,
        'data' => [
            'ordersn' => $orderRef,
            'status' => 'SHIPPED',
        ],
    ];

    $recorder->recordShopee($statusPayload, true, $shopId, 3, $orderRef, 'SHIPPED');

    $trackingPayload = [
        'code' => 4,
        'shop_id' => $shopId,
        'timestamp' => 1_700_000_100,
        'data' => [
            'ordersn' => $orderRef,
            'tracking_no' => 'TRK-001',
        ],
    ];

    $recorder->recordShopee(
        $trackingPayload,
        true,
        $shopId,
        4,
        $orderRef,
        'tracking:TRK-001',
    );

    expect(MarketplaceWebhookEvent::query()->count())->toBe(2);

    $trackingEvent = MarketplaceWebhookEvent::query()
        ->where('order_status', 'tracking:TRK-001')
        ->first();

    expect($trackingEvent)->not->toBeNull();

    $order = MarketplaceChannelOrder::query()
        ->where('channel', 'shopee')
        ->where('order_ref', $orderRef)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->order_status)->toBe('SHIPPED');
});

it('accepts shopee tracking push into inbox with tracking ingest status', function () {
    if (! Schema::hasTable('shopee_push_inbox')) {
        $this->markTestSkipped('shopee_push_inbox migration not loaded');
    }

    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.callback_url' => '',
        'services.shopee_push.store_inbox' => true,
        'services.shopee_push.shadow_enabled' => false,
        'marketplace.webhook.unified_storage' => true,
    ]);

    $body = json_encode([
        'code' => 4,
        'shop_id' => 88,
        'timestamp' => 1_700_000_200,
        'data' => [
            'ordersn' => 'WEB-TRK-1',
            'tracking_no' => 'SPX-WEB-1',
        ],
    ]);

    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_Authorization' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertSuccessful();

    $inbox = ShopeePushInbox::query()->where('ordersn', 'WEB-TRK-1')->first();
    expect($inbox)->not->toBeNull()
        ->and($inbox->order_status)->toBe('tracking:SPX-WEB-1');

    $event = MarketplaceWebhookEvent::query()
        ->where('order_ref', 'WEB-TRK-1')
        ->where('event_type', '4')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->order_status)->toBe('tracking:SPX-WEB-1');
});