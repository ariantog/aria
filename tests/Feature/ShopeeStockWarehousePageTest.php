<?php

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
    Permission::findOrCreate('addrbook-warehouse-items', 'web');
});

it('shows shopee sellable columns when warehouse is mapped', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Shopee']);
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 0,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 99,
        'shopee_warehouse_name' => 'Pickup WH',
    ]);

    $item = Item::factory()->create([
        'code' => 'SP-TEST-M',
        'shopee_item_id' => 555001,
        'shopee_model_id' => 777001,
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
            ->with([555001])
            ->andReturn([
                555001 => [
                    [
                        'model_id' => 777001,
                        'model_sku' => 'SP-TEST-M',
                        'stock_info_v2' => [
                            'summary_info' => ['total_reserved_stock' => 1],
                            'seller_stock' => [
                                ['location_id' => 'IDZ', 'stock' => 4],
                            ],
                        ],
                    ],
                ],
            ]);
    });

    $html = $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('SP sell')
        ->toContain('Pickup WH')
        ->toContain('data-copy-col="sp_sellable"')
        ->toContain('loadMarketplaceStock');

    $this->actingAs($user)
        ->postJson(route('addrbook.type.items.marketplace-stock', ['warehouse', $warehouse->id]), [
            'item_ids' => [$item->id],
        ])
        ->assertOk()
        ->assertJsonPath('shopee.stocks.'.$item->id.'.sellable', 4)
        ->assertJsonPath('shopee.stocks.'.$item->id.'.reserved', 1);
});

it('does not call shopee api during warehouse items page render', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 0,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 99,
        'shopee_warehouse_name' => 'Pickup WH',
    ]);

    $item = Item::factory()->create(['shopee_item_id' => 555001]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);

    $this->mock(ShopeeStockApiService::class, function ($mock) {
        $mock->shouldNotReceive('modelsByItemIds');
    });

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk();
});

it('requires shopee-stock-view permission for item shopee tab', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('items-list');

    $item = Item::factory()->create();

    $this->actingAs($user)
        ->get(route('items.shopee', $item))
        ->assertForbidden();
});

it('renders item shopee tab when permitted', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-view');

    $item = Item::factory()->create(['code' => 'TAB-SHOPEE']);

    $openApi = app(\App\Services\Shopee\ShopeeStockOpenApiService::class);
    $this->mock(ShopeeStockApiService::class, function ($mock) use ($openApi) {
        $mock->shouldReceive('isReady')->andReturn(false);
        $mock->shouldReceive('openApiClient')->andReturn($openApi);
    });

    $this->actingAs($user)
        ->get(route('items.shopee', $item))
        ->assertOk()
        ->assertSee('Shopee Product Link', false);
});
