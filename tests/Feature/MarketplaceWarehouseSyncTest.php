<?php

use App\Models\Addrbook;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Setting;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWarehouseSyncBackfill;
use App\Services\Marketplace\MarketplaceWarehouseSyncResolver;
use App\Services\Marketplace\MarketplaceWarehouseSyncService;
use App\Services\TikTokShop\TikTokShopOAuthService;
use Illuminate\Support\Facades\Http;

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

it('flashes concrete partner error when tiktok warehouse sync returns empty', function (): void {
    config([
        'tiktok_shop.driver' => 'live',
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.api_base_url' => 'https://open-api.tiktokglobalshop.com',
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth',
            'value' => [
                'access_token' => 'live-access-token',
                'refresh_token' => 'refresh-token',
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        '*' => Http::response([
            'code' => 401001,
            'message' => 'scope not granted',
            'data' => [],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('marketplace.warehouse-sync.create', ['channel' => 'tiktok']))
        ->post(route('marketplace.warehouse-sync.store', ['channel' => 'tiktok']), [
            'shop_ref' => '749111',
            'shop_cipher' => 'cipher-abc',
            'shop_name' => 'CA TikTok',
        ])
        ->assertRedirect(route('marketplace.warehouse-sync.create', ['channel' => 'tiktok']));

    $msg = (string) $response->getSession()->get('errorMessage');
    expect($msg)->toContain('401001')
        ->and($msg)->toContain('scope not granted')
        ->and($msg)->toContain('seller.logistics');
});

it('creates manual tiktok warehouse row and opens matrix', function (): void {
    $user = User::factory()->create();
    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    $this->actingAs($user)
        ->post(route('marketplace.warehouse-sync.tiktok.manual'), [
            'shop_ref' => $shopRef,
            'shop_cipher' => 'cipher-manual',
            'shop_name' => 'TTS CITOS',
            'platform_warehouse_id' => $platformWhId,
            'platform_warehouse_name' => 'CITOS',
        ])
        ->assertRedirect(route('marketplace.warehouse-sync.matrix', [
            'channel' => 'tiktok',
            'shopRef' => $shopRef,
        ]));

    $row = MarketplaceWarehouseSync::query()
        ->where('shop_ref', $shopRef)
        ->where('platform_warehouse_id', $platformWhId)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->platform_warehouse_name)->toBe('CITOS')
        ->and(($row->meta ?? [])['manual'] ?? false)->toBeTrue();
});

it('renders unified index with channel tabs', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.warehouse-sync.index', ['channel' => 'tiktok']))
        ->assertOk()
        ->assertSee('data-testid="marketplace-warehouse-sync-index"', false)
        ->assertSee('data-testid="marketplace-warehouse-sync-tabs"', false);
});