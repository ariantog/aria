<?php

use App\Models\Addrbook;
use App\Models\Shopeesync;
use App\Services\Marketplace\ShopeeSyncWarehouseResolver;

it('maps export shop to aria warehouse via product_location_id on sync row', function () {
    $citos = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE, 'name' => 'Store - CITOS']);
    $bsd = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE, 'name' => 'Store - BSD']);

    $shopId = 1925605693;

    Shopeesync::create([
        'warehouse_id' => $citos->id,
        'shop_id' => $shopId,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'ID00XPBYZ',
        'shopee_warehouse_id' => 100028423,
        'shopee_warehouse_name' => 'CITOS',
    ]);

    Shopeesync::create([
        'warehouse_id' => $bsd->id,
        'shop_id' => $shopId,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'IDXXBSD1',
        'shopee_warehouse_id' => 100099999,
        'shopee_warehouse_name' => 'BSD',
    ]);

    $resolver = app(ShopeeSyncWarehouseResolver::class);

    $pushData = [
        'item_list' => [
            ['product_location_id' => ['IDXXBSD1']],
        ],
    ];

    expect($resolver->ariaWarehouseIdForIngest($shopId, $pushData))->toBe($bsd->id);
});

it('leaves warehouse unset when several pickup rows share shop and push has no location', function () {
    $w1 = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $w2 = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $shopId = 555;

    Shopeesync::create([
        'warehouse_id' => $w1->id,
        'shop_id' => $shopId,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'A',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'WH1',
    ]);
    Shopeesync::create([
        'warehouse_id' => $w2->id,
        'shop_id' => $shopId,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'B',
        'shopee_warehouse_id' => 2,
        'shopee_warehouse_name' => 'WH2',
    ]);

    expect(app(ShopeeSyncWarehouseResolver::class)->ariaWarehouseIdForIngest($shopId, []))->toBe(0);
});