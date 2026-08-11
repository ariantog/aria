<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.jubelio.active' => true,
        'services.jubelio.url' => 'https://api.jubelio.com/login',
        'services.jubelio.email' => 'test@example.com',
        'services.jubelio.password' => 'secret',
        'services.jubelio.verify_ssl' => false,
        'services.jubelio.token_ttl_hours' => 10,
    ]);
});

it('renders jubelio connection page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.token.index'))
        ->assertSuccessful()
        ->assertSee('Jubelio Koneksi')
        ->assertSee('Refresh Token');
});

it('refreshes token manually from connection page', function () {
    Http::fake([
        'https://api.jubelio.com/login' => Http::response(['token' => 'fresh-manual-token'], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.token.refresh'))
        ->assertRedirect(route('jubelio.token.index'))
        ->assertSessionHas('success');

    $stored = Setting::where('slug', JubelioService::TOKEN_SETTING_SLUG)->value('value');
    expect($stored['token'])->toBe('fresh-manual-token');
});

it('reactively refreshes token when api returns 401 and retries', function () {
    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'stale-token',
            'expires_at' => now()->addHours(5)->toDateTimeString(),
        ],
    ]);

    Http::fake([
        'https://api2.jubelio.com/sales/orders/so-retry' => Http::sequence()
            ->push(['message' => 'Unauthorized'], 401)
            ->push([
                'salesorder_id' => 'so-retry',
                'salesorder_no' => 'INV-RETRY',
                'status' => 'SHIPPED',
            ], 200),
        'https://api.jubelio.com/login' => Http::response(['token' => 'fresh-reactive-token'], 200),
    ]);

    $result = app(JubelioService::class)->fetchSalesOrder('so-retry');

    expect($result['salesorder_no'])->toBe('INV-RETRY');

    $stored = Setting::where('slug', JubelioService::TOKEN_SETTING_SLUG)->value('value');
    expect($stored['token'])->toBe('fresh-reactive-token');
});

it('records failure when refresh is rejected repeatedly', function () {
    Http::fake([
        'https://api.jubelio.com/login' => Http::response(['message' => 'Invalid credentials'], 401),
    ]);

    $service = app(JubelioService::class);
    expect($service->refreshToken())->toBeNull();

    $status = $service->getConnectionStatus();
    expect($status['last_auth_error'])->not->toBeNull()
        ->and($status['consecutive_failures'])->toBeGreaterThan(0);
});

it('check connection command reports success when login and api work', function () {
    Http::fake([
        'https://api.jubelio.com/login' => Http::response(['token' => 'cron-token'], 200),
        'https://api2.jubelio.com/locations/*' => Http::response(['data' => []], 200),
    ]);

    $this->artisan('jubelio:check-connection')->assertSuccessful();

    $stored = Setting::where('slug', JubelioService::TOKEN_SETTING_SLUG)->value('value');
    expect($stored['last_api_check_ok'])->toBeTrue();
});

it('does not break existing jubelio get orders flow with reactive refresh', function () {
    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'stale-token',
            'expires_at' => now()->addHours(5)->toDateTimeString(),
        ],
    ]);

    Http::fake([
        'https://api2.jubelio.com/sales/orders/*' => Http::sequence()
            ->push(['message' => 'Unauthorized'], 401)
            ->push([
                'totalCount' => 1,
                'data' => [[
                    'salesorder_id' => 'so-poll',
                    'salesorder_no' => 'INV-POLL-REFRESH',
                    'internal_status' => 'SHIPPED',
                    'is_canceled' => 'N',
                ]],
            ], 200),
        'https://api.jubelio.com/login' => Http::response(['token' => 'refreshed-for-poll'], 200),
    ]);

    config(['services.jubelio.active' => true, 'services.jubelio.poll_days' => 7]);

    $this->artisan('jubelio:poll-missing-orders')->assertSuccessful();

    expect(\App\Models\Jubelioorder::where('invoice', 'INV-POLL-REFRESH')->exists())->toBeTrue();
});
