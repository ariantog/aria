<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceWarehouseSync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\TikTokShop\TikTokShopItemLinkApplier;
use App\Services\TikTokShop\TikTokShopItemSearchService;
use App\Services\TikTokShop\TikTokShopOpenApiService;

beforeEach(function () {
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_24_120000_add_tiktok_shop_item_link_columns_to_items.php']);
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_23_160000_install_marketplace_warehouse_syncs_table.php']);

    app(PermissionGenerator::class)->generateForModule('TikTokShop');
    app(PermissionGenerator::class)->generateForModule('Item');
    app(PermissionGenerator::class)->generateForModule('Addrbook');

    config(['tiktok_shop.driver' => 'mock']);
    config(['tiktok_shop.shop_cipher' => 'MOCK_TTS_SHOP_CIPHER']);

    User::factory()->create();
});

it('applies tiktok product and sku ids on item', function () {
    $item = Item::factory()->create([
        'code' => 'MOCK-TTS-SKU-01',
        'tiktok_shop_product_id' => null,
        'tiktok_shop_sku_id' => null,
    ]);

    $applier = app(TikTokShopItemLinkApplier::class);
    $result = $applier->apply($item, '7490000000000000101', '7491000000000000101');

    expect($result['ok'])->toBeTrue();

    $item->refresh();
    expect($item->tiktok_shop_product_id)->toBe('7490000000000000101');
    expect($item->tiktok_shop_sku_id)->toBe('7491000000000000101');
});

it('mock driver returns search rows for aria sku', function () {
    $item = Item::factory()->create(['code' => 'MOCK-TTS-SKU-01']);

    $search = app(TikTokShopItemSearchService::class);
    $rows = $search->searchForItem($item, 'MOCK-TTS-SKU-01');

    expect($rows)->not->toBeEmpty();
    expect($rows[0]['exact_match'] ?? false)->toBeTrue();
    expect($rows[0]['seller_sku'])->toBe('MOCK-TTS-SKU-01');
});

it('renders tiktok shop link UI and saves link from search', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'items-list',
        'items-edit',
        'tiktok-shop-item-link',
    ]);

    $item = Item::factory()->create(['code' => 'MOCK-TTS-SKU-01']);

    $this->actingAs($user)
        ->get(route('items.tiktok-shop', $item))
        ->assertOk()
        ->assertSee('TikTok Shop / Tokopedia link', false);

    $search = app(TikTokShopItemSearchService::class);
    $rows = $search->searchForItem($item, 'MOCK-TTS-SKU-01');
    $row = $rows[0];

    $this->actingAs($user)
        ->post(route('items.tiktok-shop-link', $item), [
            'tiktok_shop_product_id' => $row['product_id'],
            'tiktok_shop_sku_id' => $row['sku_id'],
            'shop_cipher' => 'MOCK_TTS_SHOP_CIPHER',
        ])
        ->assertRedirect(route('items.tiktok-shop', $item));

    $item->refresh();
    expect($item->tiktok_shop_product_id)->toBe($row['product_id']);
    expect($item->tiktok_shop_sku_id)->toBe($row['sku_id']);
});

it('shows warehouse tiktok unlinked banner when warehouse is mapped', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'addrbook-warehouse-items',
        'tiktok-shop-view',
        'items-list',
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create();

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => 'shop-ref-1',
        'platform_warehouse_id' => 'WH-1',
        'warehouse_id' => $warehouse->id,
        'commerce_platform' => '',
    ]);

    $item = Item::factory()->create([
        'code' => 'WH-TTS-UNLINKED',
        'tiktok_shop_product_id' => null,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertSee('data-testid="warehouse-items-tiktok-unlinked-banner"', false);
});

it('search uses open api service on mock driver', function () {
    $openApi = app(TikTokShopOpenApiService::class);
    $products = $openApi->searchProductsBySellerSkus('MOCK_TTS_SHOP_CIPHER', ['SKU-ABC']);

    expect($products)->not->toBeEmpty();
    expect($products[0]['skus'][0]['seller_sku'] ?? null)->toBe('SKU-ABC');
});