<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\TikTokShop\TikTokShopOAuthService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.service_id' => '7558320901155333121',
        'tiktok_shop.authorize_url' => 'https://services.tiktokshop.com/open/authorize',
        'tiktok_shop.redirect_url' => 'https://aria3.corenationactive.com/tiktok-shop/oauth/callback',
        'tiktok_shop.token_base_url' => 'https://auth.tiktok-shops.com/api/v2/token',
    ]);
});

it('builds seller authorize url with service_id', function (): void {
    $oauth = app(TikTokShopOAuthService::class);
    $url = $oauth->buildAuthorizeUrl('state-abc');

    expect($url)->toContain('services.tiktokshop.com/open/authorize')
        ->and($url)->toContain('service_id=7558320901155333121')
        ->and($url)->toContain('state=state-abc');
});

it('exchanges auth code and persists tokens to settings', function (): void {
    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'access_token' => 'tts-access',
                'refresh_token' => 'tts-refresh',
                'access_token_expire_in' => now()->addDays(3)->getTimestamp(),
                'refresh_token_expire_in' => now()->addDays(30)->getTimestamp(),
            ],
        ]),
    ]);

    $oauth = app(TikTokShopOAuthService::class);
    $result = $oauth->exchangeAuthCode('TTP_testcode123');

    expect($result)->not->toBeNull();
    $stored = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('tts-access')
        ->and($stored['refresh_token'])->toBe('tts-refresh');
});

it('allows omnichannel settings user to start authorize redirect', function (): void {
    app(\App\Services\PermissionGenerator::class)->generateForModule('Omnichannel');
    $user = User::factory()->create();
    $user->givePermissionTo('omnichannel-settings');

    $response = $this->actingAs($user)->get(route('tiktok-shop.authorize', ['return' => 'omnichannel']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('open/authorize');
});

it('handles oauth callback and stores token', function (): void {
    app(\App\Services\PermissionGenerator::class)->generateForModule('Omnichannel');
    $user = User::factory()->create();
    $user->givePermissionTo('omnichannel-settings');

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'access_token' => 'from-callback',
                'refresh_token' => 'from-refresh',
                'access_token_expire_in' => now()->addDays(2)->getTimestamp(),
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->withSession([App\Support\TikTokShopOAuthAuthorize::SESSION_RETURN_ROUTE => 'omnichannel.settings.index'])
        ->get(route('tiktok-shop.oauth.callback', ['code' => 'TTP_auth123']));

    $response->assertRedirect(route('omnichannel.settings.index', ['tab' => 'connections']));

    $stored = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'] ?? null)->toBe('from-callback');
});