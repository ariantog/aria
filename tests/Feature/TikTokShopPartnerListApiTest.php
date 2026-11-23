<?php

use App\Models\Setting;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'tiktok_shop.driver' => 'live',
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.api_base_url' => 'https://open-api.tiktokglobalshop.com',
        'tiktok_shop.api_version' => '202309',
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
});

it('lists authorized shops from partner authorization api with signed query', function (): void {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = (string) $request->url();

        expect($url)->toContain('/authorization/202309/shops');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($query)->toHaveKeys(['app_key', 'timestamp', 'sign'])
            ->and($query['app_key'])->toBe('test-app-key');
        expect($request->hasHeader('x-tts-access-token', 'live-access-token'))->toBeTrue();

        return Http::response([
            'code' => 0,
            'data' => [
                'shops' => [
                    [
                        'id' => '749111',
                        'cipher' => 'cipher-abc',
                        'name' => 'CA TikTok',
                    ],
                ],
            ],
        ]);
    });

    $shops = app(TikTokShopOpenApiService::class)->listAuthorizedShops();

    expect($shops)->toHaveCount(1)
        ->and($shops[0])->toMatchArray([
            'id' => '749111',
            'cipher' => 'cipher-abc',
            'name' => 'CA TikTok',
        ]);
});

it('lists seller warehouses for shop cipher from logistics api', function (): void {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = (string) $request->url();

        expect($url)->toContain('/logistics/202309/warehouses');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($query['shop_cipher'])->toBe('cipher-abc');

        return Http::response([
            'code' => 0,
            'data' => [
                'warehouses' => [
                    [
                        'id' => '70001',
                        'name' => 'Jakarta WH',
                        'type' => 'SALES_WAREHOUSE',
                        'effect_status' => 'ENABLED',
                    ],
                ],
            ],
        ]);
    });

    $rows = app(TikTokShopOpenApiService::class)->listSellerWarehouses('cipher-abc');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe('70001')
        ->and($rows[0]['name'])->toBe('Jakarta WH');
});

it('returns mock shop and warehouse fixtures when driver is mock', function (): void {
    config(['tiktok_shop.driver' => 'mock']);

    $service = app(TikTokShopOpenApiService::class);

    $shops = $service->listAuthorizedShops();
    expect($shops)->not->toBeEmpty();

    $cipher = (string) $shops[0]['cipher'];
    $warehouses = $service->listSellerWarehouses($cipher);

    expect($warehouses)->not->toBeEmpty();
    Http::assertNothingSent();
});

it('renders tiktok shop sync create with partner shop picker', function () {
    config(['tiktok_shop.driver' => 'mock']);

    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.warehouse-sync.create', ['channel' => 'tiktok']))
        ->assertOk()
        ->assertSee('data-testid="tiktok-shop-sync-create"', false)
        ->assertSee('Pilih toko dari Partner API', false)
        ->assertSee('Sinkron gudang', false);
});