<?php

use App\Models\JubelioProxyClient;
use App\Models\Setting;
use App\Services\Jubelio\JubelioProxyService;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.jubelio.active' => true,
        'services.jubelio.url' => 'https://api.jubelio.com/login',
        'services.jubelio.email' => 'test@example.com',
        'services.jubelio.password' => 'secret',
        'services.jubelio.verify_ssl' => false,
    ]);

    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'server-jubelio-token',
            'expires_at' => now()->addDay()->toDateTimeString(),
        ],
    ]);
});

/**
 * @return array{0: JubelioProxyClient, 1: string}
 */
function jubelioProxyClient(array $overrides = []): array
{
    [$plain, $hash, $prefix] = JubelioProxyClient::generateCredentials();

    $client = JubelioProxyClient::query()->create(array_merge([
        'name' => 'test-bot-'.uniqid(),
        'token_prefix' => $prefix,
        'token_hash' => $hash,
        'allowed_apis' => ['orders.list', 'orders.detail', 'inventory.list'],
        'scopes' => [],
        'write_enabled' => false,
        'active' => true,
    ], $overrides));

    return [$client, $plain];
}

it('rejects missing bearer token with 401', function (): void {
    $this->postJson('/api/jubelio-proxy', ['api' => 'orders.list'])
        ->assertUnauthorized()
        ->assertJson(['ok' => false, 'status' => 401]);
});

it('rejects unknown api with 403', function (): void {
    [, $token] = jubelioProxyClient();

    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'evil.forward', 'params' => []])
        ->assertForbidden()
        ->assertJson(['ok' => false, 'status' => 403]);
});

it('rejects api outside client allowlist', function (): void {
    [, $token] = jubelioProxyClient(['allowed_apis' => ['orders.list']]);

    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'inventory.list', 'params' => ['page' => 1]])
        ->assertForbidden()
        ->assertJson(['ok' => false, 'error' => 'API not allowed for this client.']);
});

it('blocks disabled write apis', function (): void {
    [, $token] = jubelioProxyClient([
        'allowed_apis' => ['stock.adjust_warehouse'],
        'write_enabled' => true,
    ]);

    config(['jubelio_proxy.allow_write' => false]);

    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'stock.adjust_warehouse', 'params' => ['location_id' => 1, 'items' => []]])
        ->assertForbidden();
});

it('never returns jubelio or aria tokens in proxy responses', function (): void {
    [, $token] = jubelioProxyClient(['allowed_apis' => ['orders.list']]);

    Http::fake([
        'https://api2.jubelio.com/sales/orders/*' => Http::response([
            'data' => [
                [
                    'salesorder_no' => 'INV-1',
                    'token' => 'server-jubelio-token',
                    'customer_phone' => '08123456789',
                ],
            ],
        ], 200),
    ]);

    $response = $this->withToken($token)
        ->postJson('/api/jubelio-proxy', [
            'api' => 'orders.list',
            'params' => ['page' => 1, 'pageSize' => 10],
        ])
        ->assertSuccessful()
        ->json();

    expect($response['ok'])->toBeTrue();
    $encoded = json_encode($response);
    expect($encoded)->not->toContain('server-jubelio-token');
    expect($response['data']['data'][0]['customer_phone'])->toBe('[redacted]');
});

it('proxies a read call and caps page size', function (): void {
    [, $token] = jubelioProxyClient();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), 'api2.jubelio.com/sales/orders')) {
            return Http::response([], 404);
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
        expect((int) ($query['pageSize'] ?? 0))->toBe(200);

        return Http::response(['totalCount' => 0, 'data' => []], 200);
    });

    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', [
            'api' => 'orders.list',
            'params' => ['page' => 1, 'pageSize' => 9999],
        ])
        ->assertSuccessful()
        ->assertJsonPath('api', 'orders.list')
        ->assertJsonPath('status', 200);
});

it('supports GET jubelio-proxy route', function (): void {
    [, $token] = jubelioProxyClient(['allowed_apis' => ['locations.list']]);

    Http::fake([
        'https://api2.jubelio.com/locations/*' => Http::response(['data' => []], 200),
    ]);

    $this->withToken($token)
        ->getJson('/api/jubelio-proxy/locations.list?page=1&pageSize=5')
        ->assertSuccessful()
        ->assertJsonPath('ok', true);
});

it('rate limits per client', function (): void {
    [$client, $token] = jubelioProxyClient(['rate_limit_per_minute' => 2]);

    Http::fake([
        'https://api2.jubelio.com/sales/orders/*' => Http::response(['data' => []], 200),
    ]);

    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'orders.list', 'params' => ['page' => 1]])
        ->assertSuccessful();
    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'orders.list', 'params' => ['page' => 2]])
        ->assertSuccessful();
    $this->withToken($token)
        ->postJson('/api/jubelio-proxy', ['api' => 'orders.list', 'params' => ['page' => 3]])
        ->assertStatus(429);

    expect($client->fresh()->last_used_at)->not->toBeNull();
});

it('uses per-client rate limit settings', function (): void {
    [$client] = jubelioProxyClient(['rate_limit_per_minute' => 15]);

    expect($client->effectiveRateLimit())->toBe(15);
});

it('lists phase 1 read api names', function (): void {
    $names = JubelioProxyService::readApiNames();
    expect($names)->toContain('orders.list')
        ->and($names)->toContain('inventory.list')
        ->and($names)->not->toContain('stock.adjust_warehouse');
});