<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOnOrderReservation;
use App\Models\Shopeesync;
use App\Models\WarehouseItem;
use App\Services\Shopee\ShopeePushSignatureVerifier;

beforeEach(function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => false,
        'marketplace.shopee_on_order.enabled' => true,
    ]);
});

it('increases on_order without jubelio placeholder when push includes item_list', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Test WH',
    ]);

    $item = Item::factory()->create(['code' => 'SKU-SHP-ON-1']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 50,
        'on_order' => 0,
    ]);

    $body = json_encode([
        'data' => [
            'ordersn' => 'SHPONORDER1',
            'status' => 'PROCESSED',
            'item_list' => [
                ['model_sku' => 'SKU-SHP-ON-1', 'model_quantity_purchased' => 3],
            ],
        ],
        'shop_id' => 9,
        'code' => 3,
        'timestamp' => 1660123127,
    ], JSON_THROW_ON_ERROR);

    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(Jubelioorder::count())->toBe(0);

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and((float) $row->quantity)->toBe(50.0)
        ->and((float) $row->on_order)->toBe(3.0)
        ->and(MarketplaceOnOrderReservation::count())->toBe(1);
});

it('does not double on_order on duplicate shopee push when reservations exist', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Test WH',
    ]);

    $item = Item::factory()->create(['code' => 'SKU-SHP-IDEM']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'on_order' => 0,
    ]);

    $body = '{"data":{"ordersn":"SHP_IDEM","status":"PROCESSED","item_list":[{"model_sku":"SKU-SHP-IDEM","model_quantity_purchased":2}]},"shop_id":9,"code":3,"timestamp":1}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);
    $headers = [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', route('shopee.push'), [], [], [], $headers, $body)->assertOk();
    $this->call('POST', route('shopee.push'), [], [], [], $headers, $body)->assertOk();

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->on_order)->toBe(2.0)
        ->and(MarketplaceOnOrderReservation::count())->toBe(1);
});

it('skips on_order when shopee push on_order flag is off', function () {
    config(['marketplace.shopee_on_order.enabled' => false]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Test WH',
    ]);

    $item = Item::factory()->create(['code' => 'SKU-SHP-OFF']);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'on_order' => 0,
    ]);

    $body = json_encode([
        'data' => [
            'ordersn' => 'SHP_OFF',
            'status' => 'PROCESSED',
            'item_list' => [
                ['model_sku' => 'SKU-SHP-OFF', 'model_quantity_purchased' => 5],
            ],
        ],
        'shop_id' => 9,
        'code' => 3,
        'timestamp' => 1,
    ], JSON_THROW_ON_ERROR);

    $authorization = app(ShopeePushSignatureVerifier::class)->sign(url(route('shopee.push', [], false)), $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $row = WarehouseItem::query()->where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first();
    expect((float) $row->on_order)->toBe(0.0);
});