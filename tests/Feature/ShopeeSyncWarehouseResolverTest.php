<?php

use App\Models\Addrbook;
use App\Models\Shopeesync;
use App\Services\Marketplace\ShopeeSyncWarehouseResolver;

it('maps pickup location to aria warehouse without shop_id on sync row', function () {
    $citos = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE, 'name' => 'Store - CITOS']);
    $bsd = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE, 'name' => 'Store - BSD']);

    Shopeesync::create([
        'warehouse_id' => $citos->id,
        'shop_id' => 0,
        'shopee_location_id' => 'ID00XPBYZ',
        'shopee_warehouse_id' => 100028423,
        'shopee_warehouse_name' => 'CITOS',
    ]);

    Shopeesync::create([
        'warehouse_id' => $bsd->id,
        'shop_id' => 0,
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

    expect($resolver->ariaWarehouseIdForIngest(1925605693, $pushData))->toBe($bsd->id);
});

it('returns zero when push has no pickup location', function () {
    Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);

    Shopeesync::create([
        'warehouse_id' => 1,
        'shop_id' => 0,
        'shopee_location_id' => 'A',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'WH1',
    ]);

    expect(app(ShopeeSyncWarehouseResolver::class)->ariaWarehouseIdForIngest(555, []))->toBe(0);
});