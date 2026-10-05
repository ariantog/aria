<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Shopeesync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeStockApiService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    app(PermissionGenerator::class)->generateForModule('Item');
    Permission::findOrCreate('items-list', 'web');
    Permission::findOrCreate('assetlancar-list', 'web');
});

it('shows shopee qty columns on item show when linked and mapped', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(['items-list', 'shopee-stock-view']);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang SP Detail']);
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 0,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 99,
        'shopee_warehouse_name' => 'Pickup WH',
    ]);

    $item = Item::factory()->create([
        'code' => 'SP-DETAIL-M',
        'shopee_item_id' => 555101,
        'shopee_model_id' => 777101,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 4,
    ]);

    $this->mock(ShopeeStockApiService::class, function ($mock) {
        $mock->shouldReceive('isReady')->andReturn(true);
        $mock->shouldReceive('modelsByItemIds')
            ->once()
            ->with([555101])
            ->andReturn([
                555101 => [
                    [
                        'model_id' => 777101,
                        'model_sku' => 'SP-DETAIL-M',
                        'stock_info_v2' => [
                            'summary_info' => ['total_reserved_stock' => 2],
                            'seller_stock' => [
                                ['location_id' => 'IDZ', 'stock' => 4],
                            ],
                        ],
                    ],
                ],
            ]);
    });

    $this->actingAs($user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('SP sell', false)
        ->assertSee('SP rsv', false)
        ->assertSee('data-copy-col="sp_sellable"', false)
        ->assertSee('Gudang SP Detail', false);
});

it('shows shopee qty columns on asset lancar show when linked', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(['assetlancar-list', 'shopee-stock-view']);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Aset SP']);
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 0,
        'shopee_location_id' => 'LOC1',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'WH',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'code' => 'ASSET-SP-M',
        'shopee_item_id' => 555102,
        'shopee_model_id' => 777102,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 3,
    ]);

    $this->mock(ShopeeStockApiService::class, function ($mock) {
        $mock->shouldReceive('isReady')->andReturn(true);
        $mock->shouldReceive('modelsByItemIds')
            ->once()
            ->with([555102])
            ->andReturn([
                555102 => [
                    [
                        'model_id' => 777102,
                        'model_sku' => 'ASSET-SP-M',
                        'stock_info_v2' => [
                            'summary_info' => ['total_reserved_stock' => 0],
                            'seller_stock' => [
                                ['location_id' => 'LOC1', 'stock' => 3],
                            ],
                        ],
                    ],
                ],
            ]);
    });

    $this->actingAs($user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('data-copy-col="sp_sellable"', false);
});

it('hides shopee columns on item show without shopee-stock-view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('items-list');

    $item = Item::factory()->create([
        'shopee_item_id' => 999,
        'shopee_model_id' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertDontSee('data-copy-col="sp_sellable"', false);
});
