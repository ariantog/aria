<?php

use App\Models\Addrbook;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWarehouseSync;
use App\Models\MarketplaceWebhookEvent;
use App\Models\User;
use App\Services\Marketplace\MarketplaceChannelOrderWarehousePersist;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasColumn('marketplace_channel_orders', 'platform_location_id')) {
        $this->markTestSkipped('Run migration 2026_11_24_140000_add_warehouse_persist_columns_to_marketplace_channel_orders');
    }

    config(['marketplace.webhook.unified_storage' => true]);
});

it('hydrates shopee order list row from shadow snapshot and persists warehouse fields', function () {
    $admin = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang - Online Sambisari']);
    $shopRef = '76610113';
    $orderRef = '261115C41C7BCD';

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
        'idempotency_key' => 'shopee:'.$shopRef.':3:'.$orderRef.':light',
        'event_type' => '3',
        'shop_ref' => $shopRef,
        'order_ref' => $orderRef,
        'order_status' => 'PROCESSED',
        'signature_ok' => true,
        'payload' => ['data' => ['ordersn' => $orderRef, 'order_status' => 'PROCESSED']],
        'received_at' => now(),
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderRef,
        'shop_ref' => $shopRef,
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
        'last_webhook_event_id' => $event->id,
    ]);

    MarketplaceOrderShadow::query()->create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => (int) $shopRef,
        'external_order_sn' => $orderRef,
        'channel_status' => 'PROCESSED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_SKIPPED,
        'channel_payload' => [
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderRef,
                        'order_status' => 'PROCESSED',
                        'item_list' => [
                            ['product_location_id' => ['IDZ']],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $index = $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'order_ref' => $orderRef]))
        ->assertOk();

    expect($index->getContent())->toContain('data-testid="omnichannel-order-gudang-loading-'.$order->id.'"');

    $this->actingAs($admin)
        ->postJson(route('omnichannel.orders.warehouse-labels'), ['ids' => [$order->id]])
        ->assertOk()
        ->assertJsonPath('labels.'.$order->id.'.aria_name', 'Gudang - Online Sambisari')
        ->assertJsonPath('labels.'.$order->id.'.platform_wh', 'Toko pusat');

    $order->refresh();
    expect($order->platform_location_id)->toBe('IDZ')
        ->and((int) $order->aria_warehouse_id)->toBe($warehouse->id);
});

it('hydrates shopee aria warehouse when only platform location is already stored', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Online - WTC']);
    $shopRef = '76610113';
    $orderRef = '2611244JK1AF1E';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => '0',
        'platform_location_id' => 'ID00UUL4Z',
        'platform_warehouse_name' => 'Online - WTC',
        'warehouse_id' => $warehouse->id,
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderRef,
        'shop_ref' => $shopRef,
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'platform_location_id' => 'ID00UUL4Z',
        'aria_warehouse_id' => 0,
    ]);

    app(MarketplaceChannelOrderWarehousePersist::class)->hydrateListPage(collect([$order]));

    $order->refresh();
    expect((int) $order->aria_warehouse_id)->toBe($warehouse->id);

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $names = $resolver->warehouseOptions();
    expect($resolver->warehouseNameForListRow($order, [], $names))->toBe('Online - WTC');
});

it('skips lazy hydrate when warehouse snapshot already persisted', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Already Stored WH']);
    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'PERSISTED-WH-1',
        'shop_ref' => '1',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'platform_location_id' => 'IDZ',
        'aria_warehouse_id' => $warehouse->id,
    ]);

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);
    $names = $resolver->warehouseOptions();

    expect($resolver->warehouseNameForListRow($order, [], $names))->toBe('Already Stored WH');

    app(MarketplaceChannelOrderWarehousePersist::class)->hydrateListPage(collect([$order]));

    $order->refresh();
    expect($order->platform_location_id)->toBe('IDZ')
        ->and((int) $order->aria_warehouse_id)->toBe($warehouse->id);
});

it('persists warehouse fields from webhook on record', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Webhook WH']);
    $shopRef = '99';
    $orderRef = 'WH-PERSIST-1';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => '0',
        'platform_location_id' => 'IDZ',
        'platform_warehouse_name' => 'WH Label',
        'warehouse_id' => $warehouse->id,
    ]);

    $recorder = app(\App\Services\Marketplace\MarketplaceWebhookRecorder::class);
    $recorder->recordShopee(
        [
            'item_list' => [
                ['product_location_id' => ['IDZ']],
            ],
        ],
        true,
        (int) $shopRef,
        3,
        $orderRef,
        'PROCESSED',
    );

    $order = MarketplaceChannelOrder::query()->where('order_ref', $orderRef)->first();
    expect($order)->not->toBeNull()
        ->and($order->platform_location_id)->toBe('IDZ')
        ->and((int) $order->aria_warehouse_id)->toBe($warehouse->id);
});