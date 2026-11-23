<?php

use App\Models\Setting;
use App\Services\TikTokShop\TikTokShopAccessTokenResolver;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'tiktok_shop.driver' => 'live',
        'tiktok_shop.app_key' => 'app-key',
        'tiktok_shop.app_secret' => 'app-secret',
        'tiktok_shop.shop_cipher' => 'cipher-global',
        'tiktok_shop.access_token' => '',
        'tiktok_shop.api_base_url' => 'https://open-api.tiktokglobalshop.com',
        'tiktok_shop.order_api_version' => '202507',
        'tiktok_shop.grok_token.enabled' => true,
        'tiktok_shop.grok_token.url' => 'https://grok-bot.test/api/tiktok/oauth',
        'tiktok_shop.grok_token.bearer_token' => 'aria-outbound-secret',
    ]);

    Setting::query()->where('slug', TikTokShopAccessTokenResolver::OAUTH_SETTING_SLUG)->delete();
});

it('fetches access token from grok and caches in settings', function (): void {
    Http::fake([
        'grok-bot.test/*' => Http::response([
            'access_token' => 'grok-live-token',
            'expires_at' => now()->addHours(4)->toIso8601String(),
        ]),
    ]);

    $resolver = app(TikTokShopAccessTokenResolver::class);
    expect($resolver->resolveAccessToken())->toBe('grok-live-token');

    $stored = Setting::getValue(TikTokShopAccessTokenResolver::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('grok-live-token');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer aria-outbound-secret')
            && str_contains($request->url(), 'grok-bot.test/api/tiktok/oauth');
    });
});

it('reuses cached grok token until near expiry', function (): void {
    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopAccessTokenResolver::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth (Grok cache)',
            'value' => [
                'access_token' => 'cached-token',
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake();

    $resolver = app(TikTokShopAccessTokenResolver::class);
    expect($resolver->resolveAccessToken())->toBe('cached-token');

    Http::assertNothingSent();
});

it('refreshes stale cached token via grok', function (): void {
    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopAccessTokenResolver::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth (Grok cache)',
            'value' => [
                'access_token' => 'stale',
                'expires_at' => now()->addMinutes(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'grok-bot.test/*' => Http::response([
            'access_token' => 'refreshed-from-grok',
            'expires_in' => 7200,
        ]),
    ]);

    $resolver = app(TikTokShopAccessTokenResolver::class);
    expect($resolver->resolveAccessToken())->toBe('refreshed-from-grok');
});

it('falls back to env access token when grok disabled', function (): void {
    config([
        'tiktok_shop.grok_token.enabled' => false,
        'tiktok_shop.access_token' => 'env-static-token',
    ]);

    Http::fake();

    $resolver = app(TikTokShopAccessTokenResolver::class);
    expect($resolver->resolveAccessToken())->toBe('env-static-token');

    Http::assertNothingSent();
});

it('passes shop_ref query to grok when provided', function (): void {
    Http::fake([
        'grok-bot.test/*' => Http::response([
            'access_token' => 'shop-specific',
            'expires_at' => now()->addHour()->toIso8601String(),
        ]),
    ]);

    app(TikTokShopAccessTokenResolver::class)->resolveAccessToken('7495540735365777507');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'shop_ref=7495540735365777507');
    });
});

it('uses grok token for live get order detail', function (): void {
    Http::fake([
        'grok-bot.test/*' => Http::response([
            'access_token' => 'order-api-token',
            'expires_at' => now()->addHours(2)->toIso8601String(),
        ]),
        'open-api.tiktokglobalshop.com/*' => Http::response([
            'code' => 0,
            'data' => [
                'orders' => [
                    [
                        'id' => 'ORDER-99',
                        'commerce_platform' => 'TOKOPEDIA',
                    ],
                ],
            ],
        ]),
    ]);

    $api = app(TikTokShopOpenApiService::class);
    $json = $api->getOrderDetail('ORDER-99', 'cipher-from-sync', 'shop-ref-1');

    expect($json)->not->toBeNull()
        ->and($api->extractCommercePlatform($json))->toBe('TOKOPEDIA');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'open-api.tiktokglobalshop.com')
            && str_contains($request->url(), 'access_token=order-api-token');
    });
});