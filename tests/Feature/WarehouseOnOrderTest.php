<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Shopeesync;
use App\Models\WarehouseItem;
use App\Services\InventoryService;
use App\Services\Marketplace\ShopeeJubelioQueuePrecreator;
use App\Services\Shopee\ShopeePushSignatureVerifier;

it('increases on_order not physical qty on shopee precreate when push includes item_list', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Test WH',
    ]);

    $item = Item::factory()->create(['code' => 'SKU-ON-ORDER-1']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 50,
        'on_order' => 0,
    ]);

    $body = json_encode([
        'data' => [
            'ordersn' => 'ONORDER_1',
            'status' => 'PROCESSED',
            'item_list' => [
                ['model_sku' => 'SKU-ON-ORDER-1', 'model_quantity_purchased' => 3],
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

    expect(Jubelioorder::where('invoice', 'SP-ONORDER_1')->exists())->toBeTrue();

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and((float) $row->quantity)->toBe(50.0)
        ->and((float) $row->on_order)->toBe(3.0);
});

it('releases on_order when inventory deducts physical stock', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'on_order' => 4,
    ]);

    app(InventoryService::class)->deduct($warehouse->id, $item, 2);

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->quantity)->toBe(8.0)
        ->and((float) $row->on_order)->toBe(2.0);
});

it('does not double on_order when precreate push is idempotent', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Test WH',
    ]);

    $item = Item::factory()->create(['code' => 'SKU-IDEM']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'on_order' => 0,
    ]);

    $body = '{"data":{"ordersn":"IDEM_SN","status":"PROCESSED","item_list":[{"model_sku":"SKU-IDEM","model_quantity_purchased":2}]},"shop_id":9,"code":3,"timestamp":1}';
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
        ->and(app(ShopeeJubelioQueuePrecreator::class)->invoiceForOrderSn('IDEM_SN'))->toBe('SP-IDEM_SN');
});