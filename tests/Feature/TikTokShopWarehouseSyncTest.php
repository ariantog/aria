<?php

use App\Models\Addrbook;
use App\Models\TiktokShopSync;
use App\Models\TiktokShopWarehouseSync;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use App\Services\TikTokShop\TikTokShopWarehouseSyncService;

beforeEach(function (): void {
    config(['tiktok_shop.driver' => 'mock']);
});

it('upserts seller warehouses and preserves aria warehouse_id on refresh', function (): void {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $shopRef = '7490000000000000123';
    $cipher = 'MOCK_TTS_SHOP_CIPHER';

    $service = app(TikTokShopWarehouseSyncService::class);
    $count = $service->refreshForShop($shopRef, $cipher, 'Mock Shop');
    expect($count)->toBeGreaterThan(0);

    $row = TiktokShopWarehouseSync::query()
        ->where('shop_ref', $shopRef)
        ->orderBy('id')
        ->first();
    expect($row)->not->toBeNull();

    $row->update(['warehouse_id' => $warehouse->id]);

    $service->refreshForShop($shopRef, $cipher, 'Mock Shop Updated');

    $row->refresh();
    expect($row->warehouse_id)->toBe($warehouse->id)
        ->and($row->shop_name)->toBe('Mock Shop Updated')
        ->and($row->last_synced_at)->not->toBeNull();
});

it('resolves aria warehouse by shop_ref and platform warehouse_id', function (): void {
    $warehousePlatform = Addrbook::factory()->warehouse()->create(['name' => 'CITOS']);
    $warehouseLegacy = Addrbook::factory()->warehouse()->create(['name' => 'Legacy WH']);

    $shopRef = '7495540735365777507';
    $platformWhId = '7392489215199020821';

    TiktokShopWarehouseSync::query()->create([
        'shop_ref' => $shopRef,
        'shop_cipher' => 'cipher-demo',
        'shop_name' => 'TTS Main',
        'platform_warehouse_id' => $platformWhId,
        'platform_warehouse_name' => 'Store CITOS',
        'warehouse_id' => $warehousePlatform->id,
    ]);

    TiktokShopSync::create([
        'shop_ref' => $shopRef,
        'shop_cipher' => 'cipher-demo',
        'shop_name' => 'TTS Main',
        'warehouse_id' => $warehouseLegacy->id,
    ]);

    $resolver = app(MarketplaceWebhookShopWarehouseResolver::class);

    expect($resolver->warehouseIdForTikTokShopRef($shopRef, $platformWhId, null))
        ->toBe($warehousePlatform->id)
        ->and($resolver->warehouseIdForTikTokShopRef($shopRef, 'other-warehouse', null))
        ->toBe($warehouseLegacy->id);

    $payload = [
        'shop_id' => $shopRef,
        'data' => [
            'order_id' => '123',
            'warehouse_id' => $platformWhId,
        ],
    ];

    expect($resolver->resolveAriaWarehouseId($shopRef, $payload))->toBe($warehousePlatform->id);
});

it('bootstraps shop sync and opens matrix page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tiktok-shop.sync.store'), [
            'shop_ref' => '7490000000000000123',
            'shop_cipher' => 'MOCK_TTS_SHOP_CIPHER',
            'shop_name' => 'Mock TikTok Shop (dev)',
        ])
        ->assertRedirect(route('tiktok-shop.sync.matrix', ['shopRef' => '7490000000000000123']));

    expect(TiktokShopWarehouseSync::query()->where('shop_ref', '7490000000000000123')->count())
        ->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('tiktok-shop.sync.matrix', ['shopRef' => '7490000000000000123']))
        ->assertOk()
        ->assertSee('data-testid="tiktok-shop-sync-matrix"', false)
        ->assertSee('Refresh dari TikTok', false);
});