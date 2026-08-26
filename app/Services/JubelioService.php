<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JubelioService
{
    public const TOKEN_SETTING_SLUG = 'jubelio_token';

    public const MAX_ALL_STOCKS_IDS = 200;

    /**
     * Authenticate with Jubelio API.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(bool $force = false): ?array
    {
        $config = config('services.jubelio');

        if (! ($config['active'] ?? false) && ! $force) {
            Log::warning('Jubelio authentication attempted but service is not active.');

            return null;
        }

        try {
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);

            if (! ($config['verify_ssl'] ?? true)) {
                $request->withoutVerifying();
            }

            $response = $request->post($config['url'], [
                'email' => $config['email'],
                'password' => $config['password'],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $message = 'HTTP '.$response->status().': '.Str::limit($response->body(), 200);
            Log::error('Jubelio authentication failed.', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            $this->recordAuthFailure($message);
        } catch (\Exception $e) {
            Log::error('Jubelio authentication error: '.$e->getMessage());
            $this->recordAuthFailure($e->getMessage());
        }

        return null;
    }

    /**
     * Get Jubelio token from cache or authenticate.
     */
    public function getCachedToken(): ?string
    {
        return Cache::remember('jubelio_token', now()->addHours($this->tokenTtlHours()), function () {
            $authData = $this->authenticate(true);

            return $authData['token'] ?? null;
        });
    }

    public function getToken(string $slug = self::TOKEN_SETTING_SLUG): ?string
    {
        $setting = Setting::where('slug', $slug)->first();

        if (! $setting) {
            Log::info('No jubelio_token setting found. Authenticating...');
            $authData = $this->authenticate(true);

            if (! $authData || ! isset($authData['token'])) {
                Log::error('Authentication failed during getToken.');

                return null;
            }

            $this->persistToken($authData['token'], $slug);

            return $authData['token'];
        }

        $value = $setting->value;
        if (! is_array($value)) {
            $value = [];
        }

        $expiresAt = isset($value['expires_at']) ? Carbon::parse($value['expires_at']) : null;

        if (! $expiresAt || $expiresAt->isPast()) {
            Log::info('Jubelio token expired or invalid. Re-authenticating...');
            $authData = $this->authenticate(true);

            if (! $authData || ! isset($authData['token'])) {
                Log::error('Re-authentication failed during getToken.', ['old_token_exists' => isset($value['token'])]);

                return null;
            }

            $this->persistToken($authData['token'], $slug);

            return $authData['token'];
        }

        return $value['token'] ?? null;
    }

    public function forgetToken(string $slug = self::TOKEN_SETTING_SLUG): void
    {
        Cache::forget('jubelio_token');

        $setting = Setting::where('slug', $slug)->first();
        if (! $setting) {
            return;
        }

        $value = is_array($setting->value) ? $setting->value : [];
        $value['expires_at'] = Carbon::now()->subMinute()->toDateTimeString();

        $setting->update(['value' => $value]);
    }

    public function refreshToken(string $slug = self::TOKEN_SETTING_SLUG): ?string
    {
        $this->forgetToken($slug);

        $authData = $this->authenticate(true);
        if (! $authData || ! isset($authData['token'])) {
            return null;
        }

        $this->persistToken($authData['token'], $slug);
        $this->recordAuthSuccess();

        return $authData['token'];
    }

    /**
     * Login + lightweight API ping for health checks.
     *
     * @return array{ok: bool, message: string}
     */
    public function checkConnection(): array
    {
        $token = $this->refreshToken();
        if (! $token) {
            $status = $this->getConnectionStatus();

            return [
                'ok' => false,
                'message' => $status['last_auth_error'] ?? 'Gagal login ke Jubelio. Periksa kredensial dan koneksi.',
            ];
        }

        $response = $this->get('https://api2.jubelio.com/locations/', [
            'page' => 1,
            'pageSize' => 1,
        ], retryOnUnauthorized: false);

        if ($response && $response->successful()) {
            $this->recordApiCheckSuccess();

            return ['ok' => true, 'message' => 'Koneksi Jubelio OK — token baru dan API merespons.'];
        }

        $message = $response
            ? 'Token didapat, tetapi API mengembalikan HTTP '.$response->status().'.'
            : 'Token didapat, tetapi permintaan API gagal.';

        $this->recordApiCheckFailure($message);

        return ['ok' => false, 'message' => $message];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $setting = Setting::where('slug', self::TOKEN_SETTING_SLUG)->first();
        $value = is_array($setting?->value) ? $setting->value : [];

        $expiresAt = isset($value['expires_at']) ? Carbon::parse($value['expires_at']) : null;
        $token = $value['token'] ?? null;

        return [
            'has_token' => filled($token),
            'token_preview' => $token ? (Str::substr($token, 0, 8).'…'.Str::substr($token, -4)) : null,
            'expires_at' => $expiresAt?->toDateTimeString(),
            'expires_in_minutes' => $expiresAt ? (int) now()->diffInMinutes($expiresAt, false) : null,
            'is_expired' => $expiresAt?->isPast() ?? true,
            'last_refreshed_at' => $value['last_refreshed_at'] ?? null,
            'last_auth_success_at' => $value['last_auth_success_at'] ?? null,
            'last_auth_error' => $value['last_auth_error'] ?? null,
            'last_auth_failure_at' => $value['last_auth_failure_at'] ?? null,
            'last_api_check_at' => $value['last_api_check_at'] ?? null,
            'last_api_check_ok' => $value['last_api_check_ok'] ?? null,
            'last_api_error' => $value['last_api_error'] ?? null,
            'consecutive_failures' => (int) ($value['consecutive_failures'] ?? 0),
            'jubelio_active' => (bool) config('services.jubelio.active'),
            'configured' => filled(config('services.jubelio.email')) && filled(config('services.jubelio.url')),
        ];
    }

    /**
     * Build an authenticated HTTP client for Jubelio API calls.
     */
    public function authenticatedRequest(): ?PendingRequest
    {
        config(['services.jubelio.active' => true]);

        $token = $this->getToken();
        if (! $token) {
            return null;
        }

        $request = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json']);

        if (! ($this->config()['verify_ssl'] ?? true)) {
            $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $url, array $query = [], bool $retryOnUnauthorized = true): ?Response
    {
        return $this->sendAuthenticated(
            fn (PendingRequest $http) => $http->get($url, $query),
            $retryOnUnauthorized,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $url, array $data = [], bool $retryOnUnauthorized = true): ?Response
    {
        return $this->sendAuthenticated(
            fn (PendingRequest $http) => $http->post($url, $data),
            $retryOnUnauthorized,
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     */
    protected function sendAuthenticated(callable $callback, bool $retryOnUnauthorized = true): ?Response
    {
        $request = $this->authenticatedRequest();
        if (! $request) {
            return null;
        }

        try {
            $response = $callback($request);
        } catch (\Exception $e) {
            Log::error('Jubelio API request error: '.$e->getMessage());

            return null;
        }

        if ($retryOnUnauthorized && $this->isUnauthorized($response)) {
            Log::info('Jubelio API rejected token (HTTP '.$response->status().'), refreshing...');
            $this->forgetToken();

            $request = $this->authenticatedRequest();
            if (! $request) {
                $this->recordApiCheckFailure('Token ditolak API dan refresh login gagal.');

                return $response;
            }

            try {
                $retryResponse = $callback($request);
            } catch (\Exception $e) {
                Log::error('Jubelio API retry error: '.$e->getMessage());

                return null;
            }

            if ($retryResponse->successful()) {
                $this->recordAuthSuccess();
            } elseif ($this->isUnauthorized($retryResponse)) {
                $this->recordApiCheckFailure('Token ditolak API meskipun sudah di-refresh.');
            }

            return $retryResponse;
        }

        return $response;
    }

    protected function isUnauthorized(Response $response): bool
    {
        return in_array($response->status(), [401, 403], true);
    }

    protected function persistToken(string $token, string $slug = self::TOKEN_SETTING_SLUG): void
    {
        $expiresAt = Carbon::now()->addHours($this->tokenTtlHours());
        $now = now()->toDateTimeString();

        $setting = Setting::where('slug', $slug)->first();
        $existing = is_array($setting?->value) ? $setting->value : [];

        $value = array_merge($existing, [
            'token' => $token,
            'expires_at' => $expiresAt->toDateTimeString(),
            'last_refreshed_at' => $now,
        ]);

        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            Setting::create([
                'group' => 'Jubelio',
                'name' => 'Jubelio Token',
                'slug' => $slug,
                'value' => $value,
            ]);
        }

        Cache::put('jubelio_token', $token, $expiresAt);
        $this->mergeTokenMeta([
            'last_auth_success_at' => $now,
            'last_auth_error' => null,
            'consecutive_failures' => 0,
        ]);
    }

    protected function recordAuthSuccess(): void
    {
        $this->mergeTokenMeta([
            'last_auth_success_at' => now()->toDateTimeString(),
            'last_auth_error' => null,
            'consecutive_failures' => 0,
        ]);
    }

    protected function recordAuthFailure(string $message): void
    {
        $status = $this->getConnectionStatus();
        $failures = ((int) ($status['consecutive_failures'] ?? 0)) + 1;

        $this->mergeTokenMeta([
            'last_auth_failure_at' => now()->toDateTimeString(),
            'last_auth_error' => $message,
            'consecutive_failures' => $failures,
        ]);
    }

    protected function recordApiCheckSuccess(): void
    {
        $this->mergeTokenMeta([
            'last_api_check_at' => now()->toDateTimeString(),
            'last_api_check_ok' => true,
            'last_api_error' => null,
            'consecutive_failures' => 0,
        ]);
    }

    protected function recordApiCheckFailure(string $message): void
    {
        $status = $this->getConnectionStatus();
        $failures = ((int) ($status['consecutive_failures'] ?? 0)) + 1;

        $this->mergeTokenMeta([
            'last_api_check_at' => now()->toDateTimeString(),
            'last_api_check_ok' => false,
            'last_api_error' => $message,
            'consecutive_failures' => $failures,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function mergeTokenMeta(array $meta): void
    {
        $setting = Setting::where('slug', self::TOKEN_SETTING_SLUG)->first();
        if (! $setting) {
            Setting::create([
                'group' => 'Jubelio',
                'name' => 'Jubelio Token',
                'slug' => self::TOKEN_SETTING_SLUG,
                'value' => $meta,
            ]);

            return;
        }

        $value = is_array($setting->value) ? $setting->value : [];
        $setting->update(['value' => array_merge($value, $meta)]);
    }

    protected function tokenTtlHours(): int
    {
        return (int) config('services.jubelio.token_ttl_hours', 10);
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        return config('services.jubelio', []);
    }

    /**
     * Fetch inventory from Jubelio API.
     *
     * @return array<string, mixed>|null
     */
    public function fetchInventory(int $page = 1, int $pageSize = 200): ?array
    {
        Log::info("Fetching inventory for page {$page}...");

        try {
            $response = $this->get('https://api2.jubelio.com/inventory/', [
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

            if (! $response) {
                Log::error('Failed to get token for fetchInventory.');

                return null;
            }

            Log::info("Jubelio API Response Status: {$response->status()}");

            if ($response->successful()) {
                return $response->json();
            }

            $errorBody = $response->body();
            Log::error('Jubelio fetch inventory failed.', [
                'status' => $response->status(),
                'response' => $errorBody,
                'page' => $page,
            ]);

            return [
                'error' => [
                    'message' => 'API Return HTTP '.$response->status(),
                    'raw' => $errorBody,
                ],
                'statusCode' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Jubelio fetch inventory error: '.$e->getMessage());

            return [
                'error' => [
                    'message' => 'Connection Exception',
                    'raw' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  list<int|string>  $jubelioItemIds
     * @return array<string, mixed>|null
     */
    public function fetchItemsAllStocks(array $jubelioItemIds): ?array
    {
        $ids = array_values($jubelioItemIds);
        if ($ids === []) {
            return ['data' => []];
        }

        $merged = [];

        foreach (array_chunk($ids, self::MAX_ALL_STOCKS_IDS) as $chunk) {
            $response = $this->fetchItemsAllStocksBatch($chunk);
            if ($response === null) {
                return null;
            }

            $merged = array_merge($merged, $response['data'] ?? []);
        }

        return ['data' => $merged];
    }

    /**
     * @param  list<int|string>  $jubelioItemIds
     * @return array<string, mixed>|null
     */
    protected function fetchItemsAllStocksBatch(array $jubelioItemIds): ?array
    {
        try {
            $response = $this->post('https://api2.jubelio.com/inventory/items/all-stocks/', [
                'ids' => array_values($jubelioItemIds),
            ]);

            if (! $response) {
                return null;
            }

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch items all-stocks failed.', [
                'status' => $response->status(),
                'response' => $response->body(),
                'count' => count($jubelioItemIds),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch items all-stocks error: '.$e->getMessage(), [
                'count' => count($jubelioItemIds),
            ]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchSalesOrders(int $page, int $pageSize, string $dateFrom, string $dateTo): ?array
    {
        try {
            $response = $this->get('https://api2.jubelio.com/sales/orders/', [
                'page' => $page,
                'pageSize' => $pageSize,
                'transactionDateFrom' => $dateFrom,
                'transactionDateTo' => $dateTo,
            ]);

            if (! $response) {
                return null;
            }

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch sales orders failed.', [
                'page' => $page,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch sales orders error: '.$e->getMessage(), ['page' => $page]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchSalesOrder(int|string $orderId): ?array
    {
        try {
            $response = $this->get('https://api2.jubelio.com/sales/orders/'.$orderId);

            if (! $response) {
                return null;
            }

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch sales order failed.', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch sales order error: '.$e->getMessage(), ['order_id' => $orderId]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchSalesReturn(int|string $returnId): ?array
    {
        try {
            $response = $this->get('https://api2.jubelio.com/sales/sales-returns/'.$returnId);

            if (! $response) {
                return null;
            }

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch sales return failed.', [
                'return_id' => $returnId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch sales return error: '.$e->getMessage(), ['return_id' => $returnId]);
        }

        return null;
    }

    /**
     * @param  list<int>  $jubelioItemIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchItemStocks(array $jubelioItemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $jubelioItemIds), fn ($id) => $id > 0)));

        if ($ids === []) {
            return [];
        }

        try {
            $response = $this->post('https://api2.jubelio.com/inventory/items/all-stocks/', [
                'ids' => $ids,
            ]);

            if (! $response || ! $response->successful()) {
                return [];
            }

            $rows = $response->json('data') ?? [];

            return collect($rows)
                ->filter(fn ($row) => isset($row['item_id']))
                ->keyBy(fn ($row) => (int) $row['item_id'])
                ->all();
        } catch (\Exception $e) {
            Log::error('Jubelio batch stock fetch error: '.$e->getMessage());

            return [];
        }
    }
}
