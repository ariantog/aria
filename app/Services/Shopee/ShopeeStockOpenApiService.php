<?php

namespace App\Services\Shopee;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopee Open Platform v2 client for the STOCK CHECKER app (product / shop APIs).
 *
 * Separate partner_id + OAuth from {@see \App\Services\ShopeeAds\ShopeeAdsApiService} (COREADS).
 */
class ShopeeStockOpenApiService
{
    public const OAUTH_SETTING_SLUG = 'shopee_stock_oauth';

    public function isConfigured(): bool
    {
        $config = config('services.shopee_stock');

        return filled($config['partner_id']) && filled($config['partner_key']);
    }

    public function hasShopAuthorization(): bool
    {
        $oauth = $this->getOAuthPayload();

        return filled($oauth['access_token'] ?? null) && filled($oauth['shop_id'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $oauth = $this->getOAuthPayload();
        $expiresAt = isset($oauth['expires_at']) ? Carbon::parse($oauth['expires_at']) : null;

        return [
            'configured' => $this->isConfigured(),
            'has_token' => filled($oauth['access_token'] ?? null),
            'shop_id' => $oauth['shop_id'] ?? null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'is_expired' => $expiresAt !== null
                ? $expiresAt->isPast()
                : ! filled($oauth['access_token'] ?? null),
            'last_error' => $oauth['last_error'] ?? null,
            'redirect_url' => $this->getOAuthRedirectUrl(),
        ];
    }

    public function buildAuthorizeUrl(): string
    {
        $config = config('services.shopee_stock');
        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->signPublic($path, $timestamp);

        return rtrim($config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => (int) $config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $config['redirect_url'],
        ]);
    }

    public function getOAuthRedirectUrl(): string
    {
        return (string) config('services.shopee_stock.redirect_url');
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
                return 'IP server ('.$matches[1].') belum di-whitelist di app STOCK CHECKER. Shopee Open Platform → STOCK CHECKER → IP Address Whitelist, lalu Authorize lagi.';
            }

            return 'IP server belum di-whitelist di app STOCK CHECKER. Tambahkan outbound IP Aria di Open Platform, lalu authorize ulang.';
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
            'partner_id' => (int) config('services.shopee_stock.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        $data = $this->parseShopeeResponse($response, 'Shopee stock token exchange');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Token exchange missing access_token: '.json_encode($data));

            return null;
        }

        $this->persistOAuth([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? null,
            'shop_id' => $shopId,
            'expires_at' => now()->addSeconds((int) ($payload['expire_in'] ?? 14400))->toIso8601String(),
            'last_error' => null,
        ]);

        return $payload;
    }

    public function refreshAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $refreshToken = $oauth['refresh_token'] ?? null;
        $shopId = (int) ($oauth['shop_id'] ?? 0);

        if (! $refreshToken || $shopId <= 0) {
            return null;
        }

        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $body = [
            'refresh_token' => $refreshToken,
            'shop_id' => $shopId,
            'partner_id' => (int) config('services.shopee_stock.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        $data = $this->parseShopeeResponse($response, 'Shopee stock token refresh');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Refresh missing access_token: '.json_encode($data));

            return null;
        }

        $this->persistOAuth([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'shop_id' => $shopId,
            'expires_at' => now()->addSeconds((int) ($payload['expire_in'] ?? 14400))->toIso8601String(),
            'last_error' => null,
        ]);

        return $payload['access_token'];
    }

    public function getAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $token = $oauth['access_token'] ?? null;
        $expiresAt = isset($oauth['expires_at']) ? Carbon::parse($oauth['expires_at']) : null;

        if ($token && $expiresAt && $expiresAt->isFuture()) {
            return $token;
        }

        return $this->refreshAccessToken();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, list<string>>  $repeatedQuery  e.g. item_status => ['NORMAL','UNLIST']
     */
    public function shopApiGet(string $path, array $query = [], array $repeatedQuery = []): Response
    {
        return $this->shopRequest('get', $path, $query, [], $repeatedQuery);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function shopApiPost(string $path, array $body = []): Response
    {
        return $this->shopRequest('post', $path, [], $body, []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeShopResponse(Response $response, string $context): ?array
    {
        return $this->parseShopeeResponse($response, $context);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $repeatedQuery
     */
    private function shopRequest(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $repeatedQuery = [],
    ): Response {
        $token = $this->getAccessToken();
        $oauth = $this->getOAuthPayload();
        $shopId = (int) ($oauth['shop_id'] ?? 0);

        if (! $token || $shopId <= 0) {
            return new Response(new \GuzzleHttp\Psr7\Response(401, [], '{"error":"not_authorized"}'));
        }

        $timestamp = time();
        $sign = $this->signShop($path, $timestamp, $token, $shopId);

        $baseQuery = [
            'partner_id' => (int) config('services.shopee_stock.partner_id'),
            'timestamp' => $timestamp,
            'access_token' => $token,
            'shop_id' => $shopId,
            'sign' => $sign,
        ];

        $url = rtrim(config('services.shopee_stock.base_url'), '/').$path;
        $request = Http::timeout(30);

        if ($method === 'get') {
            if ($repeatedQuery === []) {
                return $request->get($url, array_merge($baseQuery, $query));
            }

            return $request->get($url.'?'.$this->encodeShopQuery(array_merge($baseQuery, $query), $repeatedQuery));
        }

        return $request->asJson()->post($url.'?'.http_build_query($baseQuery), $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, list<string>>  $repeatedQuery
     */
    private function encodeShopQuery(array $query, array $repeatedQuery): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }

        foreach ($repeatedQuery as $key => $values) {
            foreach ($values as $value) {
                $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postPublic(string $path, int $timestamp, array $body): Response
    {
        $sign = $this->signPublic($path, $timestamp);
        $url = rtrim(config('services.shopee_stock.base_url'), '/').$path;

        return Http::timeout(30)->asJson()->post($url.'?'.http_build_query([
            'partner_id' => (int) config('services.shopee_stock.partner_id'),
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]), $body);
    }

    private function signPublic(string $path, int $timestamp): string
    {
        $partnerId = (string) config('services.shopee_stock.partner_id');
        $baseString = $partnerId.$path.$timestamp;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_stock.partner_key'));
    }

    private function signShop(string $path, int $timestamp, string $accessToken, int $shopId): string
    {
        $partnerId = (string) config('services.shopee_stock.partner_id');
        $baseString = $partnerId.$path.$timestamp.$accessToken.$shopId;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_stock.partner_key'));
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
                'group' => 'shopee_stock',
                'name' => 'Shopee Stock OAuth',
                'value' => $payload,
            ]
        );
    }

    private function recordOAuthError(string $message): void
    {
        $oauth = $this->getOAuthPayload();
        $oauth['last_error'] = $message;
        $this->persistOAuth($oauth);
        Log::error('Shopee Stock OAuth error: '.$message);
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
