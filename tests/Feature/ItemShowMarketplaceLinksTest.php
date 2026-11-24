<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Services\PermissionGenerator;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Item');
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    app(PermissionGenerator::class)->generateForModule('TikTokShop');
    Permission::findOrCreate('items-list', 'web');
    Permission::findOrCreate('assetLancar-list', 'web');
    Permission::findOrCreate('shopee-stock-view', 'web');
    Permission::findOrCreate('tiktok-shop-view', 'web');
    Permission::findOrCreate('tiktok-shop-item-link', 'web');
});

it('shows marketplace links card with ids and tab routes when linked', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'items-list',
        'shopee-stock-view',
        'tiktok-shop-view',
    ]);

    $item = Item::factory()->create([
        'jubelio_item_id' => 88001,
        'shopee_item_id' => 40623293040,
        'shopee_model_id' => 912345,
        'tiktok_shop_product_id' => '7490888123',
        'tiktok_shop_sku_id' => '7490888999',
    ]);

    $this->actingAs($user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="item-marketplace-links-card"', false)
        ->assertSee('Marketplace links', false)
        ->assertSee('data-testid="item-marketplace-jubelio-id"', false)
        ->assertSee('88001', false)
        ->assertSee('data-testid="item-marketplace-shopee-id"', false)
        ->assertSee('40623293040/912345', false)
        ->assertSee('data-testid="item-marketplace-tiktok-id"', false)
        ->assertSee('7490888123', false)
        ->assertSee('7490888999', false)
        ->assertSee(route('items.jubelio', $item), false)
        ->assertSee(route('items.shopee', $item), false)
        ->assertSee(route('items.tiktok-shop', $item), false)
        ->assertSee('data-testid="item-marketplace-jubelio-link"', false)
        ->assertSee('Kelola', false);
});

it('shows not linked copy on item show for unlinked marketplaces', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'items-list',
        'shopee-stock-view',
        'tiktok-shop-item-link',
    ]);

    $item = Item::factory()->create([
        'jubelio_item_id' => 0,
        'shopee_item_id' => 0,
        'shopee_model_id' => 0,
        'tiktok_shop_product_id' => null,
        'tiktok_shop_sku_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="item-marketplace-jubelio-unlinked"', false)
        ->assertSee('data-testid="item-marketplace-shopee-unlinked"', false)
        ->assertSee('data-testid="item-marketplace-tiktok-unlinked"', false)
        ->assertSee('Not linked', false)
        ->assertSee('data-testid="item-marketplace-jubelio-link"', false)
        ->assertSee('data-testid="item-marketplace-shopee-link"', false)
        ->assertSee('data-testid="item-marketplace-tiktok-link"', false);
});

it('hides shopee and tiktok marketplace rows without their permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('items-list');

    $item = Item::factory()->create([
        'shopee_item_id' => 111,
        'tiktok_shop_product_id' => 'prod-only',
    ]);

    $this->actingAs($user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="item-marketplace-links-card"', false)
        ->assertSee('data-testid="item-marketplace-jubelio-row"', false)
        ->assertDontSee('data-testid="item-marketplace-shopee-row"', false)
        ->assertDontSee('data-testid="item-marketplace-tiktok-row"', false)
        ->assertDontSee(route('items.shopee', $item), false);
});

it('includes marketplace links card on asset lancar show', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['assetLancar-list', 'shopee-stock-view']);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'jubelio_item_id' => 44002,
        'shopee_item_id' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('data-testid="item-marketplace-links-card"', false)
        ->assertSee('44002', false)
        ->assertSee(route('items.jubelio', $item), false);
});