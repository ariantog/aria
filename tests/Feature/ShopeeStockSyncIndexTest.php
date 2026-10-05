<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeStockOpenApiService;
use App\Services\ShopeeAds\ShopeeAdsApiService;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    config([
        'services.shopee_stock.partner_id' => '2047227',
        'services.shopee_stock.partner_key' => 'test-stock-key',
    ]);
});

it('shows not connected and authorize when stock oauth is missing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->get(route('shopee.sync.index'))
        ->assertOk()
        ->assertSee('data-testid="shopee-stock-oauth-status"', false)
        ->assertSee('Not connected', false)
        ->assertSee('data-testid="shopee-stock-authorize-button"', false)
        ->assertDontSee('data-testid="shopee-stock-reauthorize-button"', false);
});

it('shows connected and hides primary authorize when stock oauth exists', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeStockOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_stock',
            'name' => 'Shopee Stock OAuth',
            'value' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'shop_id' => 424242,
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ]
    );

    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->get(route('shopee.sync.index'))
        ->assertOk()
        ->assertSee('Connected', false)
        ->assertSee('424242', false)
        ->assertSee('data-testid="shopee-stock-reauthorize-button"', false)
        ->assertDontSee('data-testid="shopee-stock-authorize-button"', false);
});

it('hints when ads oauth exists but stock oauth does not', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdsApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_ads',
            'name' => 'Shopee Ads OAuth',
            'value' => [
                'access_token' => 'ads-token',
                'shop_id' => 111,
            ],
        ]
    );

    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->get(route('shopee.sync.index'))
        ->assertOk()
        ->assertSee('Shopee Ads (COREADS) sudah terhubung', false);
});
