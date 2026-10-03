<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_stock.partner_id' => '2047227',
        'services.shopee_stock.partner_key' => 'test-stock-partner-key',
        'services.shopee_stock.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_stock.redirect_url' => 'https://cdn.corenationactive.com/shopeestockbot.php',
    ]);
});

it('uses cdn shopeestockbot relay as default stock oauth redirect', function () {
    $api = app(ShopeeStockOpenApiService::class);

    expect($api->getOAuthRedirectUrl())->toBe('https://cdn.corenationactive.com/shopeestockbot.php');
});

it('stores stock oauth tokens separately from ads', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'stock-access',
            'refresh_token' => 'stock-refresh',
            'expire_in' => 14400,
        ]),
    ]);

    $api = app(ShopeeStockOpenApiService::class);
    $api->exchangeAuthCode('good-code', 777);

    $stored = Setting::getValue(ShopeeStockOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('stock-access')
        ->and($stored['shop_id'])->toBe(777);

    $ads = Setting::getValue(\App\Services\ShopeeAds\ShopeeAdsApiService::OAUTH_SETTING_SLUG, []);
    expect($ads)->toBe([]);
});

it('does not overwrite existing ads oauth when stock tokens are saved', function () {
    Setting::query()->updateOrCreate(
        ['slug' => \App\Services\ShopeeAds\ShopeeAdsApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_ads',
            'name' => 'Shopee Ads OAuth',
            'value' => [
                'access_token' => 'ads-access-keep',
                'refresh_token' => 'ads-refresh-keep',
                'shop_id' => 12345,
            ],
        ]
    );

    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'stock-access-new',
            'refresh_token' => 'stock-refresh-new',
            'expire_in' => 14400,
        ]),
    ]);

    app(ShopeeStockOpenApiService::class)->exchangeAuthCode('stock-code', 888);

    $ads = Setting::getValue(\App\Services\ShopeeAds\ShopeeAdsApiService::OAUTH_SETTING_SLUG, []);
    expect($ads['access_token'])->toBe('ads-access-keep')
        ->and($ads['shop_id'])->toBe(12345);

    $stock = Setting::getValue(ShopeeStockOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stock['access_token'])->toBe('stock-access-new')
        ->and($stock['shop_id'])->toBe(888);
});
