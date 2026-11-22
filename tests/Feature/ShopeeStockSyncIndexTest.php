<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeStockOpenApiService;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-key',
        'services.shopee_admin.redirect_url' => 'https://aria3.corenationactive.com/shopee-admin/oauth/callback',
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

it('warns when redirect url contains bot or php', function () {
    config([
        'app.url' => 'https://aria3.corenationactive.com',
        'services.shopee_admin.redirect_url' => 'https://cdn.corenationactive.com/shopeecoreadminbot.php',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeStockOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'token',
                'shop_id' => 76610113,
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ],
    );

    $this->actingAs($user)
        ->get(route('shopee.sync.index'))
        ->assertOk()
        ->assertSee('shopeecoreadminbot.php', false)
        ->assertSee('SHOPEE_ADMIN_REDIRECT_URL', false)
        ->assertSee('https://aria3.corenationactive.com/shopee-admin/oauth/callback', false);
});

it('shows connected when admin oauth exists for stock api', function () {
    Setting::query()->updateOrCreate(
        ['slug' => ShopeeStockOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'shared-token',
                'shop_id' => 111,
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
        ->assertSee('111', false)
        ->assertSee('CORENATION ADMIN OAuth', false);
});
