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

it('falls back to unpackaged model search when search_item returns no ids', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'POST' && str_contains($url, 'search_item')) {
            return Http::response([
                'error' => '',
                'response' => ['item_id_list' => [], 'total_count' => 0],
            ]);
        }

        if ($method === 'POST' && str_contains($url, 'search_unpackaged_model_list')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'model_list' => [
                        [
                            'item_id' => 70001,
                            'item_name' => 'Sized SKU',
                            'model_id' => 80001,
                            'unpackaged_sku_id' => 'AJD-CX90324-05-S',
                        ],
                    ],
                ],
            ]);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $rows = app(ShopeeStockApiService::class)->searchItems('AJD-CX90324-05-S');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item_id'])->toBe(70001)
        ->and($rows[0]['model_id'])->toBe(80001);
});
