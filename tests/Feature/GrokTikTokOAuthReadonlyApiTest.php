<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\TikTokShop\TikTokShopOAuthService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'export-readonly', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'api.readonly.tiktok.oauth', 'guard_name' => 'web']);

    config([
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.token_base_url' => 'https://auth.tiktok-shops.com/api/v2/token',
    ]);
});

function grokTikTokApiUser(): User
{
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo(['export-readonly', 'api.readonly.tiktok.oauth']);

    return $user;
}

it('returns tiktok oauth token for bearer-authenticated grok user', function (): void {
    $user = grokTikTokApiUser();
    config([
        'readonly_export.bearer_token' => 'grok-tiktok-bearer',
        'readonly_export.username' => $user->username,
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth',
            'value' => [
                'access_token' => 'live-access-token',
                'refresh_token' => 'secret-refresh',
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]
    );

    $response = $this->withToken('grok-tiktok-bearer')
        ->getJson('/api/readonly/tiktok/oauth');

    $response->assertSuccessful()
        ->assertJson([
            'access_token' => 'live-access-token',
            'partner_app' => 'tiktok_shop',
        ]);

    expect($response->json())->not->toHaveKey('refresh_token');
});

it('refreshes near-expiry token and never exposes refresh_token in json', function (): void {
    $user = grokTikTokApiUser();
    $this->actingAs($user);

    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth',
            'value' => [
                'access_token' => 'stale-access',
                'refresh_token' => 'refresh-keep',
                'expires_at' => now()->addMinutes(2)->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'access_token' => 'refreshed-access',
                'refresh_token' => 'refresh-new',
                'access_token_expire_in' => now()->addHours(4)->getTimestamp(),
            ],
        ]),
    ]);

    $response = $this->getJson('/api/readonly/tiktok/oauth');

    $response->assertSuccessful()
        ->assertJsonPath('access_token', 'refreshed-access');

    expect($response->json())->not->toHaveKey('refresh_token');

    $stored = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('refreshed-access');
});

it('returns 401 when shop oauth requires re-authorization', function (): void {
    $user = grokTikTokApiUser();
    $this->actingAs($user);

    $response = $this->getJson('/api/readonly/tiktok/oauth');

    $response->assertUnauthorized()
        ->assertJsonPath('error', 'tiktok_not_connected');
});

it('returns 503 when refresh fails', function (): void {
    $user = grokTikTokApiUser();
    $this->actingAs($user);

    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth',
            'value' => [
                'access_token' => 'expired-access',
                'refresh_token' => 'bad-refresh',
                'expires_at' => now()->subHour()->toIso8601String(),
            ],
        ]
    );

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 10003,
            'message' => 'invalid refresh token',
        ], 200),
    ]);

    $response = $this->getJson('/api/readonly/tiktok/oauth');

    $response->assertStatus(503)
        ->assertJsonPath('error', 'tiktok_token_unavailable');
    expect($response->json())->not->toHaveKey('access_token');
});

it('forbids tiktok oauth endpoint without api.readonly.tiktok.oauth permission', function (): void {
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo('export-readonly');
    $this->actingAs($user);

    $this->getJson('/api/readonly/tiktok/oauth')->assertForbidden();
});

it('exchanges auth code via bearer and never returns tokens in json', function (): void {
    $user = grokTikTokApiUser();
    config([
        'readonly_export.bearer_token' => 'grok-tiktok-bearer',
        'readonly_export.username' => $user->username,
    ]);

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'access_token' => 'exchanged-access',
                'refresh_token' => 'exchanged-refresh',
                'access_token_expire_in' => now()->addDays(3)->getTimestamp(),
            ],
        ]),
    ]);

    $response = $this->withToken('grok-tiktok-bearer')
        ->postJson('/api/readonly/tiktok/oauth/exchange', ['code' => 'TTP_relay123']);

    $response->assertSuccessful()
        ->assertJson(['ok' => true])
        ->assertJsonStructure(['expires_at', 'expires_at_unix']);

    expect($response->json())->not->toHaveKeys(['access_token', 'refresh_token']);

    $stored = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);
    expect($stored['access_token'])->toBe('exchanged-access')
        ->and($stored['refresh_token'])->toBe('exchanged-refresh');
});

it('accepts auth_code alias in exchange body', function (): void {
    $user = grokTikTokApiUser();
    config([
        'readonly_export.bearer_token' => 'grok-tiktok-bearer',
        'readonly_export.username' => $user->username,
    ]);

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'access_token' => 'alias-access',
                'refresh_token' => 'alias-refresh',
                'access_token_expire_in' => now()->addDay()->getTimestamp(),
            ],
        ]),
    ]);

    $response = $this->withToken('grok-tiktok-bearer')
        ->postJson('/api/readonly/tiktok/oauth/exchange', ['auth_code' => 'TTP_alias']);

    $response->assertSuccessful()->assertJson(['ok' => true]);
});

it('returns last_error when exchange fails', function (): void {
    $user = grokTikTokApiUser();
    config([
        'readonly_export.bearer_token' => 'grok-tiktok-bearer',
        'readonly_export.username' => $user->username,
    ]);

    Http::fake([
        'auth.tiktok-shops.com/*' => Http::response([
            'code' => 10001,
            'message' => 'invalid auth code',
        ], 200),
    ]);

    $response = $this->withToken('grok-tiktok-bearer')
        ->postJson('/api/readonly/tiktok/oauth/exchange', ['code' => 'TTP_bad']);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'error' => 'tiktok_exchange_failed',
        ]);

    expect($response->json('message'))->toContain('invalid auth code');

    $stored = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);
    expect($stored['last_error'] ?? null)->toContain('invalid auth code');
});

it('forbids exchange without api.readonly.tiktok.oauth permission', function (): void {
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo('export-readonly');
    config([
        'readonly_export.bearer_token' => 'grok-only-export',
        'readonly_export.username' => $user->username,
    ]);

    $this->withToken('grok-only-export')
        ->postJson('/api/readonly/tiktok/oauth/exchange', ['code' => 'TTP_x'])
        ->assertForbidden();
});