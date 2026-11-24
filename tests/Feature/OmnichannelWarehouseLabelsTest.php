<?php

use App\Models\Addrbook;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWarehouseSync;
use App\Models\MarketplaceWebhookEvent;
use App\Models\User;
use App\Services\PermissionGenerator;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
});

it('hydrates warehouse labels for current orders list rows via json endpoint', function () {
    $admin = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Lazy WH']);
    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => $platformWhId,
        'platform_location_id' => '',
        'platform_warehouse_name' => 'TTS Hub',
        'warehouse_id' => $warehouse->id,
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:'.$shopRef.':1:LAZY-WH-1:1',
        'event_type' => '1',
        'shop_ref' => $shopRef,
        'order_ref' => 'LAZY-WH-1',
        'order_status' => 'AWAITING_SHIPMENT',
        'signature_ok' => true,
        'payload' => [
            'data' => [
                'warehouse_id' => $platformWhId,
            ],
        ],
        'received_at' => now(),
    ]);

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'LAZY-WH-1',
        'shop_ref' => $shopRef,
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('omnichannel.orders.warehouse-labels'), ['ids' => [$order->id]])
        ->assertOk()
        ->assertJsonPath('labels.'.$order->id.'.aria_name', 'Lazy WH')
        ->assertJsonPath('labels.'.$order->id.'.platform_wh', 'TTS Hub')
        ->assertJsonPath('labels.'.$order->id.'.unmapped', false);

    $order->refresh();
    expect((int) $order->aria_warehouse_id)->toBe($warehouse->id);
});

it('queues lazy warehouse load when aria warehouse id is not set yet', function () {
    $admin = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Deferred WH']);

    if (! \Illuminate\Support\Facades\Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')) {
        $this->markTestSkipped('platform_warehouse_id column missing');
    }

    $order = MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'LAZY-PENDING-1',
        'shop_ref' => '7495540735365777507',
        'order_status' => 'AWAITING_SHIPMENT',
        'platform_warehouse_id' => 'wh-only',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $html = $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'order_ref' => 'LAZY-PENDING-1']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-testid="omnichannel-order-gudang-loading-'.$order->id.'"');
});

it('hides shop column when only one mapped shop exists even with many webhook shop refs', function () {
    $admin = User::factory()->create();

    \App\Models\Shopeesync::query()->create([
        'shop_id' => 100,
        'warehouse_id' => Addrbook::factory()->warehouse()->create()->id,
        'shopee_location_id' => 'LOC1',
        'shopee_warehouse_name' => 'Main',
    ]);

    foreach (['200', '300'] as $ghostShop) {
        MarketplaceWebhookEvent::query()->create([
            'channel' => 'shopee',
            'idempotency_key' => 'shopee:'.$ghostShop.':3:GHOST:1',
            'event_type' => '3',
            'shop_ref' => $ghostShop,
            'order_ref' => 'GHOST',
            'order_status' => 'PROCESSED',
            'signature_ok' => true,
            'payload' => [],
            'received_at' => now(),
        ]);
    }

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'MAPPED-ONLY-1',
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'ops_bucket' => 'unprocessed']))
        ->assertOk()
        ->assertDontSee('data-testid="omnichannel-order-shop-ref-', false)
        ->assertDontSee('data-testid="omnichannel-filter-shop-ref"', false);
});