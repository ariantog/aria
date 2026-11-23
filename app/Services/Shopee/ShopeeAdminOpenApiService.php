<?php

namespace App\Services\Shopee;

use App\Models\Setting;
use App\Support\ShopeeAdminOAuthStore;
use App\Support\ShopeeOAuthExpiry;
use App\Support\ShopeeOAuthRedirect;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopee Open Platform v2 client for CORENATION ADMIN (push / omnichannel order APIs).
 *
 * Separate partner_id + OAuth from {@see ShopeeStockOpenApiService} (STOCK CHECKER).
 */
class ShopeeAdminOpenApiService
{
    public const OAUTH_SETTING_SLUG = 'shopee_admin_oauth';

    public function isConfigured(): bool
    {
        $config = config('services.shopee_admin');

        return filled($config['partner_id']) && filled($config['partner_key']);
    }

    public function hasShopAuthorization(): bool
    {
        return $this->hasShopAuthorizationFor(ShopeeAdminOAuthStore::primaryShopId($this->getOAuthPayload()));
    }

    public function hasShopAuthorizationFor(int $shopId): bool
    {
        $creds = ShopeeAdminOAuthStore::credentialsForShop($this->getOAuthPayload(), $shopId);

        return $shopId > 0
            && (filled($creds['access_token'] ?? null) || filled($creds['refresh_token'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $oauth = $this->getOAuthPayload();
        $expiresAt = ShopeeOAuthExpiry::accessExpiresAt($oauth);

        return [
            'configured' => $this->isConfigured(),
            'has_token' => filled($oauth['access_token'] ?? null) || filled($oauth['refresh_token'] ?? null),
            'shop_id' => $oauth['shop_id'] ?? null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_minutes' => $expiresAt ? (int) now()->diffInMinutes($expiresAt, false) : null,
            'is_expired' => ShopeeOAuthExpiry::requiresReauthorization($oauth),
            'can_refresh' => filled($oauth['refresh_token'] ?? null) && (int) ($oauth['shop_id'] ?? 0) > 0,
            'last_error' => $oauth['last_error'] ?? null,
            'redirect_url' => $this->getOAuthRedirectUrl(),
            'redirect_url_recommended' => ShopeeOAuthRedirect::defaultAdminCallbackUrl(),
            'redirect_url_console_rejected' => ShopeeOAuthRedirect::consoleRejects($this->getOAuthRedirectUrl()),
        ];
    }

    public function buildAuthorizeUrl(): string
    {
        $config = config('services.shopee_admin');
        $redirect = (string) ($config['redirect_url'] ?? '');
        ShopeeOAuthRedirect::warnIfConsoleRejects($redirect);

        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->signPublic($path, $timestamp);

        return rtrim($config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => (int) $config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

    public function getOAuthRedirectUrl(): string
    {
        return (string) config('services.shopee_admin.redirect_url');
    }

    public function getLastOAuthError(): ?string
    {
        $oauth = $this->getOAuthPayload();

        return isset($oauth['last_error']) ? (string) $oauth['last_error'] : null;
    }

    public function formatOAuthErrorForUser(?string $detail): ?string
    {
        if ($detail === null || $detail === '') {
            return null;
        }

        if (str_contains($detail, 'source_ip_undeclared')) {
            if (preg_match('/Request Source IP \(([^)]+)\)/', $detail, $matches)) {
                return 'IP server ('.$matches[1].') belum di-whitelist di app CORENATION ADMIN. Shopee Open Platform → CORENATION ADMIN → IP Address Whitelist, lalu Authorize lagi.';
            }

            return 'IP server belum di-whitelist di app CORENATION ADMIN. Tambahkan outbound IP Aria di Open Platform, lalu authorize ulang.';
        }

        if (str_contains(strtolower($detail), 'invalid_partner_id')) {
            return $detail.' — Periksa SHOPEE_ADMIN_PARTNER_ID / SHOPEE_ADMIN_REDIRECT_URL cocok dengan Console CORENATION ADMIN (2053195): '.ShopeeOAuthRedirect::defaultAdminCallbackUrl().' (tanpa "bot" atau ".php" di URL). Jangan campur kredensial STOCK CHECKER.';
        }

        return $detail;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exchangeAuthCode(string $code, int $shopId): ?array
    {
        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $body = [
            'code' => $code,
            'shop_id' => $shopId,
            'partner_id' => (int) config('services.shopee_admin.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        $data = $this->parseShopeeResponse($response, 'Shopee admin token exchange');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Token exchange missing access_token: '.json_encode($data));

            return null;
        }

        $shopTokens = [
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds(ShopeeOAuthExpiry::expireInSecondsFromPayload($payload))->toIso8601String(),
            'last_refreshed_at' => now()->toIso8601String(),
        ];

        $merged = ShopeeAdminOAuthStore::mergeShopAuthorization($this->getOAuthPayload(), $shopId, $shopTokens);
        $merged['last_error'] = null;
        $this->persistOAuth($merged);

        return $payload;
    }

    public function refreshAccessToken(?int $shopId = null): ?string
    {
        $oauth = $this->getOAuthPayload();
        $shopId = $shopId ?? ShopeeAdminOAuthStore::primaryShopId($oauth);
        $creds = ShopeeAdminOAuthStore::credentialsForShop($oauth, $shopId);
        $refreshToken = $creds['refresh_token'] ?? null;

        if (! $refreshToken || $shopId <= 0) {
            return null;
        }

        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $body = [
            'refresh_token' => $refreshToken,
            'shop_id' => $shopId,
            'partner_id' => (int) config('services.shopee_admin.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        $data = $this->parseShopeeResponse($response, 'Shopee admin token refresh');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Refresh missing access_token: '.json_encode($data));

            return null;
        }

        $shopTokens = [
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'expires_at' => now()->addSeconds(ShopeeOAuthExpiry::expireInSecondsFromPayload($payload))->toIso8601String(),
            'last_refreshed_at' => now()->toIso8601String(),
        ];

        $merged = ShopeeAdminOAuthStore::mergeShopTokensAfterRefresh($oauth, $shopId, $shopTokens);
        $merged['last_error'] = null;
        $this->persistOAuth($merged);

        return $payload['access_token'];
    }

    public function getAccessToken(?int $shopId = null): ?string
    {
        $oauth = $this->getOAuthPayload();
        $shopId = $shopId ?? ShopeeAdminOAuthStore::primaryShopId($oauth);
        $creds = ShopeeAdminOAuthStore::credentialsForShop($oauth, $shopId);
        $token = $creds['access_token'] ?? null;

        if ($token && ! ShopeeOAuthExpiry::shouldRefreshAccessToken($creds)) {
            return $token;
        }

        return $this->refreshAccessToken($shopId);
    }

    /**
     * Proactive refresh for cron / health checks (no API ping).
     *
     * @return array{ok: bool, message: string, refreshed: bool}
     */
    public function checkConnection(): array
    {
        $oauth = $this->getOAuthPayload();
        $shopIds = ShopeeAdminOAuthStore::authorizedShopIds($oauth);

        if ($shopIds === []) {
            return [
                'ok' => false,
                'message' => 'Shopee belum diotorisasi — tidak ada access_token / refresh_token.',
                'refreshed' => false,
            ];
        }

        $anyRefreshed = false;
        $failures = [];

        foreach ($shopIds as $shopId) {
            $creds = ShopeeAdminOAuthStore::credentialsForShop($this->getOAuthPayload(), $shopId);
            if (! ShopeeOAuthExpiry::shouldRefreshAccessToken($creds)) {
                continue;
            }

            $token = $this->refreshAccessToken($shopId);
            if ($token) {
                $anyRefreshed = true;
            } else {
                $failures[] = $shopId;
            }
        }

        if ($failures !== []) {
            $detail = $this->formatOAuthErrorForUser($this->getLastOAuthError());

            return [
                'ok' => false,
                'message' => $detail ?? 'Gagal refresh token Shopee shop(s): '.implode(', ', $failures),
                'refreshed' => $anyRefreshed,
            ];
        }

        if ($anyRefreshed) {
            return [
                'ok' => true,
                'message' => 'Token akses Shopee berhasil di-refresh ('.count($shopIds).' shop).',
                'refreshed' => true,
            ];
        }

        $primary = ShopeeAdminOAuthStore::primaryShopId($this->getOAuthPayload());
        $creds = ShopeeAdminOAuthStore::credentialsForShop($this->getOAuthPayload(), $primary);
        $expiresAt = ShopeeOAuthExpiry::accessExpiresAt($creds);

        return [
            'ok' => true,
            'message' => 'Token Shopee masih berlaku'.($expiresAt ? ' hingga '.$expiresAt->timezone(config('app.timezone'))->format('d M Y H:i') : '').' ('.count($shopIds).' shop).',
            'refreshed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function shopApiGet(string $path, array $query = [], ?int $shopId = null): Response
    {
        $oauth = $this->getOAuthPayload();
        $shopId = $shopId ?? ShopeeAdminOAuthStore::primaryShopId($oauth);
        $token = $this->getAccessToken($shopId);

        if (! $token || $shopId <= 0) {
            return new Response(new \GuzzleHttp\Psr7\Response(401, [], '{"error":"not_authorized"}'));
        }

        $timestamp = time();
        $sign = $this->signShop($path, $timestamp, $token, $shopId);

        $baseQuery = [
            'partner_id' => (int) config('services.shopee_admin.partner_id'),
            'timestamp' => $timestamp,
            'access_token' => $token,
            'shop_id' => $shopId,
            'sign' => $sign,
        ];

        $url = rtrim(config('services.shopee_admin.base_url'), '/').$path;

        return Http::timeout(30)->get($url, array_merge($baseQuery, $query));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderDetail(
        string $orderSn,
        ?int $shopId = null,
        string $optionalFields = 'order_status,item_list,total_amount',
    ): ?array {
        $orderSn = trim($orderSn);
        if ($orderSn === '') {
            return null;
        }

        $response = $this->shopApiGet('/api/v2/order/get_order_detail', [
            'order_sn_list' => $orderSn,
            'response_optional_fields' => $optionalFields,
        ], $shopId);

        return $this->decodeShopResponse($response, 'get_order_detail');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function getOrderList(int $shopId, array $query): ?array
    {
        if ($shopId <= 0) {
            return null;
        }

        $response = $this->shopApiGet('/api/v2/order/get_order_list', $query, $shopId);

        return $this->decodeShopResponse($response, 'get_order_list');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeShopResponse(Response $response, string $context): ?array
    {
        return $this->parseShopeeResponse($response, $context);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postPublic(string $path, int $timestamp, array $body): Response
    {
        $sign = $this->signPublic($path, $timestamp);
        $url = rtrim(config('services.shopee_admin.base_url'), '/').$path;

        return Http::timeout(30)->asJson()->post($url.'?'.http_build_query([
            'partner_id' => (int) config('services.shopee_admin.partner_id'),
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]), $body);
    }

    private function signPublic(string $path, int $timestamp): string
    {
        $partnerId = (string) config('services.shopee_admin.partner_id');
        $baseString = $partnerId.$path.$timestamp;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_admin.partner_key'));
    }

    private function signShop(string $path, int $timestamp, string $accessToken, int $shopId): string
    {
        $partnerId = (string) config('services.shopee_admin.partner_id');
        $baseString = $partnerId.$path.$timestamp.$accessToken.$shopId;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_admin.partner_key'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getOAuthPayload(): array
    {
        $value = Setting::getValue(self::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistOAuth(array $payload): void
    {
        Setting::query()->updateOrCreate(
            ['slug' => self::OAUTH_SETTING_SLUG],
            [
                'group' => 'shopee_admin',
                'name' => 'Shopee Admin OAuth',
                'value' => $payload,
            ]
        );
    }

    private function recordOAuthError(string $message): void
    {
        $oauth = $this->getOAuthPayload();
        $oauth['last_error'] = $message;
        $this->persistOAuth($oauth);
        Log::error('Shopee Admin OAuth error: '.$message);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseShopeeResponse(Response $response, string $context): ?array
    {
        if (! $response->successful()) {
            $this->recordOAuthError($context.' failed (HTTP '.$response->status().'): '.$response->body());

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            $this->recordOAuthError($context.' returned invalid JSON: '.$response->body());

            return null;
        }

        if (isset($data[0]) && is_array($data[0]) && array_key_exists('error', $data[0])) {
            $data = $data[0];
        }

        $error = trim((string) ($data['error'] ?? ''));
        if ($error !== '') {
            $message = trim((string) ($data['message'] ?? ''));
            $detail = $message !== '' ? "{$error} — {$message}" : $error;
            $requestId = trim((string) ($data['request_id'] ?? ''));
            if ($requestId !== '') {
                $detail .= " (request_id: {$requestId})";
            }
            $this->recordOAuthError($context.' Shopee API error: '.$detail);

            return null;
        }

        $this->clearOAuthLastError();

        return $data;
    }

    private function clearOAuthLastError(): void
    {
        $oauth = $this->getOAuthPayload();
        if (! array_key_exists('last_error', $oauth) || $oauth['last_error'] === null || $oauth['last_error'] === '') {
            return;
        }

        $oauth['last_error'] = null;
        $this->persistOAuth($oauth);
    }
}