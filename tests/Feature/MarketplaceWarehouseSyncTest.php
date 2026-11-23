<?php

use App\Models\Addrbook;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWarehouseSyncBackfill;
use App\Services\Marketplace\MarketplaceWarehouseSyncResolver;
use App\Services\Marketplace\MarketplaceWarehouseSyncService;

beforeEach(function (): void {
    config(['tiktok_shop.driver' => 'mock']);
});

it('backfills shopee_syncs rows into marketplace_warehouse_syncs', function (): void {
    $warehouse = Addrbook::factory()->warehouse()->create();

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 55,
        'shopee_location_id' => 'LOC-1',
        'shopee_warehouse_id' => 9001,
        'shopee_warehouse_name' => 'Pickup BSD',
    ]);

    app(MarketplaceWarehouseSyncBackfill::class)->run();

    $row = MarketplaceWarehouseSync::query()
        ->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE)
        ->where('platform_warehouse_id', '9001')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->warehouse_id)->toBe($warehouse->id)
        ->and($row->shop_ref)->toBe('55')
        ->and($row->platform_location_id)->toBe('LOC-1');
});

it('upserts tiktok seller warehouses and preserves aria mapping on refresh', function (): void {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $shopRef = '7490000000000000123';
    $cipher = 'MOCK_TTS_SHOP_CIPHER';

    $service = app(MarketplaceWarehouseSyncService::class);
    $count = $service->refreshTikTokShop($shopRef, $cipher, 'Mock Shop');
    expect($count)->toBeGreaterThan(0);

    $row = MarketplaceWarehouseSync::query()
        ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
        ->where('shop_ref', $shopRef)
        ->orderBy('id')
        ->first();
    expect($row)->not->toBeNull();

    $row->update(['warehouse_id' => $warehouse->id]);

    $service->refreshTikTokShop($shopRef, $cipher, 'Mock Shop Updated');

    $row->refresh();
    expect($row->warehouse_id)->toBe($warehouse->id)
        ->and($row->shop_name)->toBe('Mock Shop Updated');
});

it('resolves warehouses via unified table with legacy fallback', function (): void {
    $warehousePlatform = Addrbook::factory()->warehouse()->create(['name' => 'CITOS']);
    $warehouseLegacy = Addrbook::factory()->warehouse()->create(['name' => 'Legacy WH']);

    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => $shopRef,
        'platform_warehouse_id' => $platformWhId,
        'platform_location_id' => '',
        'platform_warehouse_name' => 'Store CITOS',
        'warehouse_id' => $warehousePlatform->id,
    ]);

    TiktokShopSync::create([
        'shop_ref' => $shopRef,
        'shop_cipher' => 'cipher-demo',
        'shop_name' => 'TTS Main',
        'warehouse_id' => $warehouseLegacy->id,
    ]);

    $resolver = app(MarketplaceWarehouseSyncResolver::class);

    expect($resolver->ariaWarehouseForTikTok($shopRef, $platformWhId, null))->toBe($warehousePlatform->id)
        ->and($resolver->ariaWarehouseForTikTok($shopRef, 'missing', null))->toBe($warehouseLegacy->id);
});

it('bootstraps tiktok shop and opens unified matrix', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('marketplace.warehouse-sync.store', ['channel' => 'tiktok']), [
            'shop_ref' => '7490000000000000123',
            'shop_cipher' => 'MOCK_TTS_SHOP_CIPHER',
            'shop_name' => 'Mock TikTok Shop (dev)',
        ])
        ->assertRedirect(route('marketplace.warehouse-sync.matrix', [
            'channel' => 'tiktok',
            'shopRef' => '7490000000000000123',
        ]));

    $this->actingAs($user)
        ->get(route('marketplace.warehouse-sync.matrix', ['channel' => 'tiktok', 'shopRef' => '7490000000000000123']))
        ->assertOk()
        ->assertSee('data-testid="tiktok-shop-sync-matrix"', false);
});

it('renders unified index with channel tabs', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.warehouse-sync.index', ['channel' => 'tiktok']))
        ->assertOk()
        ->assertSee('data-testid="marketplace-warehouse-sync-index"', false)
        ->assertSee('data-testid="marketplace-warehouse-sync-tabs"', false);
});