<?php

use App\Models\Addrbook;
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