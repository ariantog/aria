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
        'tiktok_shop.product_api_version' => '202502',
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

it('calls product search with 202502 path, signed query, and access token header', function (): void {
    Http::fake([
        'https://open-api.tiktokglobalshop.com/product/202502/products/search*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'products' => [
                    [
                        'id' => '7490000000000000101',
                        'skus' => [
                            ['id' => '7491000000000000101', 'seller_sku' => 'SKU-ABC'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $products = app(TikTokShopOpenApiService::class)
        ->searchProductsBySellerSkus('test-shop-cipher', ['SKU-ABC']);

    expect($products)->toHaveCount(1)
        ->and($products[0]['skus'][0]['seller_sku'])->toBe('SKU-ABC');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $url = (string) $request->url();

        expect($url)->toContain('/product/202502/products/search');

        parse_str((string) parse_url((string) $request->toPsrRequest()->getUri(), PHP_URL_QUERY), $query);

        expect($query)->toHaveKeys(['app_key', 'shop_cipher', 'page_size', 'timestamp', 'sign'])
            ->and($query)->not->toHaveKey('access_token')
            ->and($query['app_key'])->toBe('test-app-key')
            ->and($query['shop_cipher'])->toBe('test-shop-cipher');

        expect($request->header('x-tts-access-token'))->toContain('live-access-token');

        $contentTypeHeaders = $request->header('Content-Type');
        expect($contentTypeHeaders)->toHaveCount(1)
            ->and($contentTypeHeaders[0])->toBe('application/json');

        $decodedBody = json_decode($request->body(), true);
        expect($decodedBody)->toBeArray()
            ->and($decodedBody['status'])->toBe('ACTIVATE')
            ->and($decodedBody['seller_skus'])->toBe(['SKU-ABC']);

        return true;
    });
});