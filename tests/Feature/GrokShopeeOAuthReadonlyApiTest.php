<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'export-readonly', 'guard_name' => 'web']);

    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.redirect_url' => 'https://aria3.corenationactive.com/shopee-admin/oauth/callback',
    ]);
});

function grokShopeeApiUser(): User
{
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo('export-readonly');

    return $user;
}

it('returns shopee oauth token for bearer-authenticated grok user', function (): void {
    $user = grokShopeeApiUser();
    config([
        'readonly_export.bearer_token' => 'grok-shopee-bearer',
        'readonly_export.username' => $user->username,
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'live-access-token',
                'refresh_token' => 'secret-refresh',
                'shop_id' => 9001,
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]
    );

    $response = $this->withToken('grok-shopee-bearer')
        ->getJson('/api/readonly/shopee/oauth');

    $response->assertSuccessful()
        ->assertJson([
            'access_token' => 'live-access-token',
            'shop_id' => 9001,
            'partner_app' => 'shopee_admin',
        ]);

    expect($response->json())->not->toHaveKey('refresh_token');
    expect($response->json('access_token'))->not->toBe('secret-refresh');
});

it('refreshes near-expiry token and never exposes refresh_token in json', function (): void {
    $user = grokShopeeApiUser();
    $this->actingAs($user);

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'stale-access',
                'refresh_token' => 'refresh-keep',
                'shop_id' => 42,
                'expires_at' => now()->addMinutes(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'response' => [
                'access_token' => 'refreshed-access',
                'refresh_token' => 'refresh-new',
                'expire_in' => 14_400,
            ],
        ]),
    ]);

    $response = $this->getJson('/api/readonly/shopee/oauth');

    $response->assertSuccessful()
        ->assertJsonPath('access_token', 'refreshed-access')
        ->assertJsonPath('shop_id', 42);

    expect($response->json())->not->toHaveKey('refresh_token');

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('refreshed-access');
});

it('returns 401 when shop oauth requires re-authorization', function (): void {
    $user = grokShopeeApiUser();
    $this->actingAs($user);

    $response = $this->getJson('/api/readonly/shopee/oauth');

    $response->assertUnauthorized()
        ->assertJsonPath('error', 'shopee_not_connected');
});

it('returns 503 when refresh fails', function (): void {
    $user = grokShopeeApiUser();
    $this->actingAs($user);

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'expired-access',
                'refresh_token' => 'bad-refresh',
                'shop_id' => 77,
                'expires_at' => now()->subHour()->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => 'error_auth',
            'message' => 'invalid refresh token',
        ], 200),
    ]);

    $response = $this->getJson('/api/readonly/shopee/oauth');

    $response->assertStatus(503)
        ->assertJsonPath('error', 'shopee_token_unavailable');
    expect($response->json())->not->toHaveKey('access_token');
});

it('forbids shopee oauth endpoint without export-readonly permission', function (): void {
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $this->actingAs($user);

    $this->getJson('/api/readonly/shopee/oauth')->assertForbidden();
});