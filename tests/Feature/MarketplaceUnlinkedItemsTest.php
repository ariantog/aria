<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Shopeesync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Marketplace\MarketplaceUnlinkedItemsService;
use App\Services\PermissionGenerator;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    app(PermissionGenerator::class)->generateForModule('TikTokShop');
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
});

it('lists shopee-unlinked items with stock in a mapped warehouse', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Unlinked Test']);
    Shopeesync::factory()->create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 12345,
    ]);

    $unlinked = Item::factory()->create([
        'code' => 'UNLINKED-SHOPEE-01',
        'shopee_item_id' => 0,
        'shopee_model_id' => 0,
    ]);
    $linked = Item::factory()->create([
        'code' => 'LINKED-SHOPEE-01',
        'shopee_item_id' => 9001,
        'shopee_model_id' => 8001,
    ]);

    foreach ([$unlinked, $linked] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 3,
        ]);
    }

    $service = app(MarketplaceUnlinkedItemsService::class);
    $page = $service->paginate([
        'warehouse_id' => $warehouse->id,
        'channel' => 'shopee',
        'stock_only' => true,
    ], 50);

    $codes = collect($page->items())->pluck('code')->all();

    expect($codes)->toContain('UNLINKED-SHOPEE-01')
        ->and($codes)->not->toContain('LINKED-SHOPEE-01');
});

it('excludes zero-qty rows when stock_only is enabled', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::factory()->create(['warehouse_id' => $warehouse->id, 'shop_id' => 99]);

    $item = Item::factory()->create([
        'code' => 'ZERO-QTY-UNLINKED',
        'shopee_item_id' => null,
        'shopee_model_id' => null,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 0,
    ]);

    $service = app(MarketplaceUnlinkedItemsService::class);

    $withStockOnly = $service->paginate([
        'warehouse_id' => $warehouse->id,
        'channel' => 'shopee',
        'stock_only' => true,
    ], 50);
    expect(collect($withStockOnly->items())->pluck('code')->all())->not->toContain('ZERO-QTY-UNLINKED');

    $catalog = $service->paginate([
        'warehouse_id' => $warehouse->id,
        'channel' => 'shopee',
        'stock_only' => false,
    ], 50);
    expect(collect($catalog->items())->pluck('code')->all())->toContain('ZERO-QTY-UNLINKED');
});

it('renders omnichannel item links page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('omnichannel-view');

    $warehouse = Addrbook::factory()->warehouse()->create();
    Shopeesync::factory()->create(['warehouse_id' => $warehouse->id, 'shop_id' => 77]);

    $this->actingAs($user)
        ->get(route('omnichannel.item-links.index', ['warehouse_id' => $warehouse->id]))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-item-links-title"', false)
        ->assertSee('data-testid="omnichannel-item-links-table"', false);
});

it('detects missing shopee and tiktok fields', function () {
    $item = Item::factory()->make([
        'shopee_item_id' => 10,
        'shopee_model_id' => 0,
        'tiktok_shop_product_id' => 'prod',
        'tiktok_shop_sku_id' => null,
    ]);

    expect(MarketplaceUnlinkedItemsService::isShopeeUnlinked($item))->toBeTrue()
        ->and(MarketplaceUnlinkedItemsService::missingShopeeFields($item))->toBe(['shopee_model_id'])
        ->and(MarketplaceUnlinkedItemsService::isTikTokUnlinked($item))->toBeTrue()
        ->and(MarketplaceUnlinkedItemsService::missingTikTokFields($item))->toBe(['tiktok_shop_sku_id']);
});