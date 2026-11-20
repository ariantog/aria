<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.redirect_url' => 'https://cdn.corenationactive.com/shopeecoreadminbot.php',
    ]);
});

it('uses coreadmin cdn relay as default oauth redirect for ads client', function () {
    $api = app(ShopeeAdsApiService::class);

    expect($api->getOAuthRedirectUrl())->toBe('https://cdn.corenationactive.com/shopeecoreadminbot.php');
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

it('stores tokens on admin slug not legacy ads slug', function () {
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

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['shop_id'])->toBe(888)
        ->and($stored['access_token'])->toBe('access-abc');

    $legacy = Setting::getValue(ShopeeAdsApiService::LEGACY_OAUTH_SETTING_SLUG, []);
    expect($legacy)->toBe([]);
});

it('migrates legacy shopee_ads_oauth into admin slug on read', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdsApiService::LEGACY_OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_ads',
            'name' => 'Shopee Ads OAuth',
            'value' => [
                'access_token' => 'legacy-ads-token',
                'refresh_token' => 'legacy-refresh',
                'shop_id' => 42,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]
    );

    $api = app(ShopeeAdsApiService::class);

    expect($api->hasShopAuthorization())->toBeTrue();

    $admin = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($admin['access_token'])->toBe('legacy-ads-token')
        ->and($admin['shop_id'])->toBe(42);
});

it('ads authorize redirects to unified admin authorize', function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeAds');
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-ads-view');

    $this->actingAs($user)
        ->get(route('shopee-ads.authorize'))
        ->assertRedirect(route('shopee-admin.authorize', ['return' => 'shopee-ads']));
});