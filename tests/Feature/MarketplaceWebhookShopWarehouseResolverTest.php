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

it('resolves tiktok list row warehouse from persisted platform_warehouse_id without webhook warehouse in payload', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Persisted WH']);
    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => $platformWhId,
        'platform_location_id' => '',
        'platform_warehouse_name' => 'Platform Cell',
        'warehouse_id' => $warehouse->id,
    ]);

    $event = MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:'.$shopRef.':9:NO-WH-PAYLOAD:1',
        'event_type' => '1',
        'shop_ref' => $shopRef,
        'order_ref' => 'NO-WH-PAYLOAD',
        'order_status' => 'AWAITING_SHIPMENT',
        'signature_ok' => true,
        'payload' => ['type' => 1, 'data' => ['order_id' => 'NO-WH-PAYLOAD']],
        'received_at' => now(),
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'NO-WH-PAYLOAD',
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => $platformWhId,
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'last_webhook_event_id' => $event->id,
    ]);

    $order->load('lastWebhookEvent');

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $map = $resolver->shopRefToWarehouseMap();
    $names = [];

    expect($resolver->warehouseNameForListRow($order, $map, $names))->toBe('Persisted WH')
        ->and($resolver->platformWarehouseLabelForListRow($order))->toBe('Platform Cell');
});

it('loads aria warehouse name by id when not present in warehouse options pluck', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Loaded By Id']);
    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);

    expect($resolver->ariaWarehouseNameById($warehouse->id, []))->toBe('Loaded By Id');
});

it('resolves shopee list row warehouse from product_location_id via matrix', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang - Online Sambisari']);
    $shopRef = '76610113';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => '0',
        'platform_location_id' => 'IDZ',
        'platform_warehouse_name' => 'Toko pusat',
        'warehouse_id' => $warehouse->id,
    ]);

    $event = MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:'.$shopRef.':3:SHOPEE-IDZ-1:1',
        'event_type' => '3',
        'shop_ref' => $shopRef,
        'order_ref' => 'SHOPEE-IDZ-1',
        'order_status' => 'PROCESSED',
        'signature_ok' => true,
        'payload' => [
            'item_list' => [
                ['product_location_id' => ['IDZ']],
            ],
        ],
        'received_at' => now(),
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'SHOPEE-IDZ-1',
        'shop_ref' => $shopRef,
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'last_webhook_event_id' => $event->id,
    ]);

    $order->load('lastWebhookEvent');

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $map = $resolver->shopRefToWarehouseMap();
    $names = $resolver->warehouseOptions();

    expect($resolver->warehouseNameForListRow($order, $map, $names))->toBe('Gudang - Online Sambisari')
        ->and($resolver->platformWarehouseLabelForListRow($order))->toBe('Toko pusat');
});

it('reads aria and platform warehouse from persisted channel order columns', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('marketplace_channel_orders', 'aria_warehouse_id')) {
        $this->markTestSkipped('aria_warehouse_id column missing');
    }

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Stored Column WH']);
    $shopRef = '76610113';

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'STORED-COLS-1',
        'shop_ref' => $shopRef,
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'platform_location_id' => 'IDZ',
        'aria_warehouse_id' => $warehouse->id,
    ]);

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => '0',
        'platform_location_id' => 'IDZ',
        'platform_warehouse_name' => 'Toko pusat',
        'warehouse_id' => $warehouse->id,
    ]);

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);

    expect($resolver->warehouseNameForListRow($order, [], []))->toBe('Stored Column WH')
        ->and($resolver->platformWarehouseLabelForListRow($order))->toBe('Toko pusat');
});