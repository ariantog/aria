<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.redirect_url' => 'https://cdn.corenationactive.com/shopeecoreadmin.php',
    ]);
});

it('uses coreadmin cdn relay as default stock oauth redirect', function () {
    $api = app(ShopeeStockOpenApiService::class);

    expect($api->getOAuthRedirectUrl())->toBe('https://cdn.corenationactive.com/shopeecoreadmin.php');
});

it('surfaces invalid_partner_id oauth hint for admin app', function () {
    $api = app(ShopeeStockOpenApiService::class);
    $detail = 'HTTP 403 invalid_partner_id';

    expect($api->formatOAuthErrorForUser($detail))
        ->toContain('invalid_partner_id')
        ->and($api->formatOAuthErrorForUser($detail))->toContain('SHOPEE_ADMIN_PARTNER_ID')
        ->and($api->formatOAuthErrorForUser($detail))->toContain('shopeecoreadmin.php');
});

it('stores stock tokens on admin slug only', function () {
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

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('stock-access')
        ->and($stored['shop_id'])->toBe(777);

    $legacyStock = Setting::getValue(ShopeeStockOpenApiService::LEGACY_OAUTH_SETTING_SLUG, []);
    expect($legacyStock)->toBe([]);
});

it('does not overwrite admin oauth when stock exchange runs after ads token exists', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'shared-access',
                'refresh_token' => 'shared-refresh',
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

    $admin = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($admin['access_token'])->toBe('stock-access-new')
        ->and($admin['shop_id'])->toBe(888);

    expect(app(ShopeeAdsApiService::class)->hasShopAuthorization())->toBeTrue();
});

it('migrates legacy shopee_stock_oauth into admin slug on read', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeStockOpenApiService::LEGACY_OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_stock',
            'name' => 'Shopee Stock OAuth',
            'value' => [
                'access_token' => 'legacy-stock-token',
                'shop_id' => 55,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]
    );

    $api = app(ShopeeStockOpenApiService::class);

    expect($api->hasShopAuthorization())->toBeTrue();

    $admin = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($admin['access_token'])->toBe('legacy-stock-token')
        ->and($admin['shop_id'])->toBe(55);
});