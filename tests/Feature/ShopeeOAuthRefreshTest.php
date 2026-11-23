<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Support\ShopeeOAuthExpiry;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.redirect_url' => 'https://aria3.corenationactive.com/shopee-admin/oauth/callback',
    ]);
});

it('does not mark oauth expired when refresh token exists but access ttl passed', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'stale-access',
                'refresh_token' => 'valid-refresh',
                'shop_id' => 1001,
                'expires_at' => now()->subHour()->toIso8601String(),
            ],
        ]
    );

    $status = app(ShopeeAdminOpenApiService::class)->getConnectionStatus();

    expect($status['is_expired'])->toBeFalse()
        ->and($status['can_refresh'])->toBeTrue()
        ->and($status['has_token'])->toBeTrue();
});

it('refresh oauth command exchanges refresh token and extends expires_at', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'old-access',
                'refresh_token' => 'refresh-abc',
                'shop_id' => 42,
                'expires_at' => now()->subMinutes(30)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'response' => [
                'access_token' => 'new-access',
                'refresh_token' => 'refresh-xyz',
                'expire_in' => 14_400,
            ],
        ]),
    ]);

    $this->artisan('shopee:refresh-oauth')->assertSuccessful();

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);

    expect($stored['access_token'])->toBe('new-access')
        ->and($stored['refresh_token'])->toBe('refresh-xyz')
        ->and(Carbon\Carbon::parse($stored['expires_at'])->isFuture())->toBeTrue();
});

it('get access token refreshes proactively inside the buffer window', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'near-expiry',
                'refresh_token' => 'refresh-abc',
                'shop_id' => 7,
                'expires_at' => now()->addMinutes(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'buffer-refreshed',
            'refresh_token' => 'refresh-abc',
            'expire_in' => 14_400,
        ]),
    ]);

    $token = app(ShopeeAdsApiService::class)->getAccessToken();

    expect($token)->toBe('buffer-refreshed');
    Http::assertSentCount(1);
});

it('aligns ads connection is_expired with admin refresh semantics', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'legacy-access',
                'refresh_token' => 'legacy-refresh',
                'shop_id' => 12,
                'expires_at' => now()->subDay()->toIso8601String(),
            ],
        ]
    );

    expect(app(ShopeeAdsApiService::class)->getConnectionStatus()['is_expired'])->toBeFalse();
    expect(ShopeeOAuthExpiry::requiresReauthorization(Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, [])))->toBeFalse();
});