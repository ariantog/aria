<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.redirect_url' => 'https://cdn.corenationactive.com/shopeecoreadmin.php',
    ]);
});

it('uses cdn shopeecoreadmin relay as default admin oauth redirect', function () {
    $api = app(ShopeeAdminOpenApiService::class);

    expect($api->getOAuthRedirectUrl())->toBe('https://cdn.corenationactive.com/shopeecoreadmin.php');
});

it('does not send bot in authorize redirect query param', function () {
    $api = app(ShopeeAdminOpenApiService::class);
    $url = $api->buildAuthorizeUrl();

    expect($url)->toContain('auth_partner')
        ->and($url)->not->toMatch('/redirect=[^&]*bot/i');
});

it('warns when admin redirect url still contains bot', function () {
    config(['services.shopee_admin.redirect_url' => 'https://cdn.example.com/shopeecoreadminbot.php']);
    Log::spy();

    app(ShopeeAdminOpenApiService::class)->buildAuthorizeUrl();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'bot'));
});

it('coreadmin cdn relay defaults to aria3 admin oauth callback', function () {
    $path = base_path('public/shopeecoreadmin.php');
    expect($path)->toBeReadableFile();
    expect(file_get_contents($path))->toContain(
        "const LARAVEL_OAUTH_CALLBACK = 'https://aria3.corenationactive.com/shopee-admin/oauth/callback';"
    );
});

it('surfaces invalid_partner_id oauth hint for admin app mismatch', function () {
    $api = app(ShopeeAdminOpenApiService::class);
    $detail = 'HTTP 403 invalid_partner_id';

    expect($api->formatOAuthErrorForUser($detail))
        ->toContain('SHOPEE_ADMIN_PARTNER_ID')
        ->and($api->formatOAuthErrorForUser($detail))->toContain('shopeecoreadmin.php');
});

it('allows shopee ads view permission to start admin authorize', function () {
    app(\App\Services\PermissionGenerator::class)->generateForModule('ShopeeAds');
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-ads-view');

    $response = $this->actingAs($user)->get(route('shopee-admin.authorize', ['return' => 'shopee-ads']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('auth_partner');
});

it('stores admin oauth tokens separately from stock', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'admin-access',
            'refresh_token' => 'admin-refresh',
            'expire_in' => 14400,
        ]),
    ]);

    $api = app(ShopeeAdminOpenApiService::class);
    $api->exchangeAuthCode('good-code', 205);

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('admin-access')
        ->and($stored['shop_id'])->toBe(205);

    $legacyStock = Setting::getValue(ShopeeStockOpenApiService::LEGACY_OAUTH_SETTING_SLUG, []);
    expect($legacyStock)->toBe([]);
});

it('admin oauth callback exchanges code without auth middleware', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'callback-access',
            'refresh_token' => 'callback-refresh',
            'expire_in' => 14400,
        ]),
    ]);

    $this->get(route('shopee-admin.oauth.callback', ['code' => 'cb-code', 'shop_id' => 999]))
        ->assertRedirect(route('omnichannel.index'))
        ->assertSessionHas('success');

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('callback-access')
        ->and($stored['shop_id'])->toBe(999);
});

it('authorize admin redirects superadmin to shopee when configured', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('shopee-admin.authorize'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('auth_partner');
});