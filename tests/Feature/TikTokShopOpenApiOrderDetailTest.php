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
        'tiktok_shop.shop_cipher' => 'test-shop-cipher',
        'tiktok_shop.api_base_url' => 'https://open-api.tiktokglobalshop.com',
        'tiktok_shop.order_api_version' => '202507',
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

it('calls get order detail with ids query, access token header, and signed query params', function (): void {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = (string) $request->url();

        expect($url)
            ->toContain('/order/202507/orders')
            ->not->toContain('/order/202507/orders/5790');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($query)->toHaveKeys(['app_key', 'ids', 'shop_cipher', 'timestamp', 'sign'])
            ->and($query)->not->toHaveKey('access_token')
            ->and($query['app_key'])->toBe('test-app-key')
            ->and($query['ids'])->toBe('5790123456789')
            ->and($query['shop_cipher'])->toBe('test-shop-cipher');

        expect($request->hasHeader('x-tts-access-token', 'live-access-token'))->toBeTrue();

        return Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'orders' => [
                    [
                        'id' => '5790123456789',
                        'order_status' => 'AWAITING_SHIPMENT',
                        'commerce_platform' => 'TIKTOK_SHOP',
                    ],
                ],
            ],
        ]);
    });

    $result = app(TikTokShopOpenApiService::class)->getOrderDetail('5790123456789');

    expect($result)->toBeArray()
        ->and($result['code'])->toBe(0)
        ->and($result['data']['orders'][0]['id'])->toBe('5790123456789');
});

it('returns null when live api responds http 200 with non-zero code', function (): void {
    Http::fake([
        '*' => Http::response([
            'code' => 105002,
            'message' => 'Invalid credentials',
        ], 200),
    ]);

    $result = app(TikTokShopOpenApiService::class)->getOrderDetail('5790123456789');

    expect($result)->toBeNull();
});