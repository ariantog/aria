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

        if ($method === 'GET' && str_contains($url, '/api/v2/product/search_item')) {
            expect($url)->toContain('item_name=')
                ->and($url)->toContain('page_size=')
                ->and($url)->toContain('item_status=NORMAL');

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

it('does not call get_item_list for plain name queries like knee', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'get_item_list')) {
            return Http::response(['error' => 'should not list'], 500);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), 'search_item')) {
            return Http::response([
                'error' => '',
                'response' => ['item_id_list' => [], 'total_count' => 0],
            ]);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $rows = app(ShopeeStockApiService::class)->searchItems('knee');

    expect($rows)->toBe([]);
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'get_item_list'));
});

it('does not call unpackaged model search when name search is empty', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if ($request->method() === 'POST' && str_contains($request->url(), 'search_unpackaged_model_list')) {
            return Http::response(['error' => 'product.error_unknown'], 200);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), 'search_item')) {
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
            expect($request->method())->toBe('GET');
            expect($url)->toContain('item_status=NORMAL')
                ->and($url)->toContain('item_status=UNLIST');

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
            parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
            $itemId = (int) ($query['item_id'] ?? 0);

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
        ->and($queries)->toContain('CX90324-05')
        ->and($queries)->toContain('AJD-CX90324');
});

it('includes parent pcode in name search fragments', function () {
    $queries = app(ShopeeStockApiService::class)->itemNameSearchQueries('KNEESUPPORT-21-NAVY-S');

    expect($queries)->toContain('KNEESUPPORT-21');
});

it('does not treat plain words as variation codes for catalog scan', function () {
    $api = app(ShopeeStockApiService::class);

    expect($api->looksLikeVariationCode('knee'))->toBeFalse()
        ->and($api->looksLikeVariationCode('kneewrap'))->toBeFalse()
        ->and($api->looksLikeVariationCode('KNEEWRAP-01-REDIRON'))->toBeTrue();
});

it('loads product by numeric shopee item id without search_item', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if ($request->method() === 'GET' && str_contains($request->url(), 'search_item')) {
            return Http::response(['error' => 'should not call search'], 500);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), 'get_item_base_info')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'item_list' => [
                        [
                            'item_id' => 844078646,
                            'item_name' => 'Knee Wrap',
                            'item_sku' => 'KNEEWRAP-01',
                        ],
                    ],
                ],
            ]);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $rows = app(ShopeeStockApiService::class)->searchItems('844078646');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item_id'])->toBe(844078646)
        ->and($rows[0]['item_name'])->toBe('Knee Wrap');
});
