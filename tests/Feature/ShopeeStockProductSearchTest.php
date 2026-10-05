<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeStockApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_stock.partner_id' => '2047227',
        'services.shopee_stock.partner_key' => 'test-stock-partner-key',
        'services.shopee_stock.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_stock.redirect_url' => 'https://cdn.corenationactive.com/shopeestockbot.php',
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeStockOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_stock',
            'name' => 'Shopee Stock OAuth',
            'value' => [
                'access_token' => 'stock-token',
                'refresh_token' => 'stock-refresh',
                'shop_id' => 424242,
                'expires_at' => now()->addHours(3)->toIso8601String(),
                'last_error' => null,
            ],
        ]
    );
});

it('hydrates search_item item_id_list via get_item_base_info', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'POST' && str_contains($url, '/api/v2/product/search_item')) {
            expect($request->hasHeader('Content-Type', 'application/json'))->toBeTrue();

            return Http::response([
                'error' => '',
                'response' => [
                    'item_id_list' => [90001, 90002],
                    'total_count' => 2,
                ],
            ]);
        }

        if ($method === 'GET' && str_contains($url, '/api/v2/product/get_item_base_info')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'item_list' => [
                        [
                            'item_id' => 90001,
                            'item_name' => 'Running Shirt',
                            'item_sku' => 'AJD-CX90324-05',
                        ],
                    ],
                ],
            ]);
        }

        return Http::response(['error' => 'unexpected', 'message' => $url], 500);
    });

    $rows = app(ShopeeStockApiService::class)->searchItems('AJD-CX90324-05-S', 10);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item_id'])->toBe(90001)
        ->and($rows[0]['item_sku'])->toBe('AJD-CX90324-05')
        ->and($rows[0]['item_name'])->toBe('Running Shirt');
});

it('does not call unpackaged model search when name search is empty', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if ($request->method() === 'POST' && str_contains($request->url(), 'search_unpackaged_model_list')) {
            return Http::response(['error' => 'product.error_unknown'], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), 'search_item')) {
            return Http::response([
                'error' => '',
                'response' => ['item_id_list' => [], 'total_count' => 0],
            ]);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $rows = app(ShopeeStockApiService::class)->searchItems('AJD-CX90324-05-S');

    expect($rows)->toBe([]);
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'search_unpackaged_model_list'));
});

it('finds exact model_sku on a catalog page', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();

        if (str_contains($url, 'get_item_list')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'item' => [
                        ['item_id' => 5001],
                        ['item_id' => 5002],
                    ],
                    'has_next_page' => true,
                    'next_offset' => 100,
                ],
            ]);
        }

        if (str_contains($url, 'get_model_list')) {
            $body = $request->data();
            $itemId = (int) ($body['item_id'] ?? 0);

            if ($itemId === 5001) {
                return Http::response([
                    'error' => '',
                    'response' => [
                        'model' => [
                            ['model_id' => 9001, 'model_sku' => 'AJD-CX90324-05-S'],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'error' => '',
                'response' => ['model' => []],
            ]);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $result = app(ShopeeStockApiService::class)->findExactModelSkuOnCatalogPage('AJD-CX90324-05-S', 0, 10, 5);

    expect($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]['item_id'])->toBe(5001)
        ->and($result['matches'][0]['model_id'])->toBe(9001)
        ->and($result['next_offset'])->toBe(100);
});

it('builds name search fragments from aria sku codes', function () {
    $queries = app(ShopeeStockApiService::class)->itemNameSearchQueries('AJD-CX90324-05-S');

    expect($queries)->toContain('AJD-CX90324-05-S')
        ->and($queries)->toContain('CX90324-05');
});
