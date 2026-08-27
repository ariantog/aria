<?php

use App\Models\Setting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_ads.partner_id' => '12345',
        'services.shopee_ads.partner_key' => 'test-partner-key',
        'services.shopee_ads.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_ads.redirect_url' => null,
    ]);
});

it('uses cdn shopeebot relay as default oauth redirect', function () {
    config(['services.shopee_ads.redirect_url' => 'https://cdn.corenationactive.com/shopeebot.php']);

    $api = app(ShopeeAdsApiService::class);

    expect($api->getOAuthRedirectUrl())->toBe('https://cdn.corenationactive.com/shopeebot.php');
});

it('surfaces shopee api error on token exchange', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => 'error_auth',
            'message' => 'Invalid code',
            'request_id' => 'req-123',
        ]),
    ]);

    $api = app(ShopeeAdsApiService::class);
    $result = $api->exchangeAuthCode('bad-code', 999);

    expect($result)->toBeNull()
        ->and($api->getLastOAuthError())->toContain('error_auth')
        ->and($api->getLastOAuthError())->toContain('Invalid code');
});

it('formats source ip undeclared error for users', function () {
    $api = app(ShopeeAdsApiService::class);
    $detail = 'Token exchange failed (HTTP 403): {"error":"source_ip_undeclared","message":"Request Source IP (153.92.8.208) is undeclared."}';

    expect($api->formatOAuthErrorForUser($detail))->toContain('153.92.8.208')
        ->and($api->formatOAuthErrorForUser($detail))->toContain('IP Address Whitelist');
});

it('stores tokens when shopee returns success payload', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'access-abc',
            'refresh_token' => 'refresh-abc',
            'expire_in' => 14400,
        ]),
    ]);

    $api = app(ShopeeAdsApiService::class);
    $result = $api->exchangeAuthCode('good-code', 888);

    expect($result)->not->toBeNull()
        ->and($result['access_token'])->toBe('access-abc')
        ->and($api->hasShopAuthorization())->toBeTrue();

    $stored = Setting::getValue(ShopeeAdsApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['shop_id'])->toBe(888)
        ->and($stored['access_token'])->toBe('access-abc');
});
