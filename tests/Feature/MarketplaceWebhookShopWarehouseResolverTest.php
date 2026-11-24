<?php

use App\Models\Addrbook;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWarehouseSync;
use App\Models\MarketplaceWebhookEvent;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;

it('resolves shop_ref to warehouse from shopee and tiktok sync tables', function () {
    $warehouseA = Addrbook::factory()->warehouse()->create();
    $warehouseB = Addrbook::factory()->warehouse()->create();

    Shopeesync::create([
        'warehouse_id' => $warehouseA->id,
        'shop_id' => 42,
        'shopee_location_id' => 'A',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Shopee A',
    ]);

    TiktokShopSync::create([
        'shop_ref' => '7495540735365777507',
        'shop_cipher' => 'cipher-demo',
        'shop_name' => 'TTS Main',
        'warehouse_id' => $warehouseB->id,
    ]);

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $map = $resolver->shopRefToWarehouseMap();

    expect($map)->toMatchArray([
        '42' => $warehouseA->id,
        '7495540735365777507' => $warehouseB->id,
    ]);

    expect($resolver->warehouseIdForShopRef('7495540735365777507'))->toBe($warehouseB->id)
        ->and($resolver->shopRefsForWarehouse($warehouseB->id))->toContain('7495540735365777507');

    $options = $resolver->warehouseOptions();
    expect($options)->toHaveKeys([(string) $warehouseA->id, (string) $warehouseB->id]);

    expect($resolver->warehouseIdForShopRef('042'))->toBe($warehouseA->id);
    expect($resolver->warehouseNameForShopRef('42', $map, $options))->toBe($warehouseA->name);
    expect($resolver->warehouseNameForShopRef('7495540735365777507', $map, $options))->toBe($warehouseB->name);
    expect($resolver->warehouseNameForShopRef('999999999', $map, $options))->toBeNull();
});

it('resolves tiktok list row warehouse from last webhook payload via matrix', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'TTS Matrix WH']);
    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => $platformWhId,
        'platform_location_id' => '',
        'platform_warehouse_name' => 'Platform Name',
        'warehouse_id' => $warehouse->id,
    ]);

    $event = MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:'.$shopRef.':9:RESOLVER-ROW-1:1',
        'event_type' => '1',
        'shop_ref' => $shopRef,
        'order_ref' => 'RESOLVER-ROW-1',
        'order_status' => 'AWAITING_SHIPMENT',
        'signature_ok' => true,
        'payload' => ['data' => ['warehouse_id' => $platformWhId]],
        'received_at' => now(),
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'RESOLVER-ROW-1',
        'shop_ref' => $shopRef,
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'last_webhook_event_id' => $event->id,
    ]);

    $order->load('lastWebhookEvent');

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $map = $resolver->shopRefToWarehouseMap();
    $names = $resolver->warehouseOptions();

    expect($resolver->warehouseNameForListRow($order, $map, $names))->toBe('TTS Matrix WH')
        ->and($resolver->platformWarehouseLabelForListRow($order))->toBe('Platform Name');
});