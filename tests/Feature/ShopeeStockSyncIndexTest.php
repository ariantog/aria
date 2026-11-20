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
        'services.shopee_admin.redirect_url' => 'https://cdn.corenationactive.com/shopeecoreadminbot.php',
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
