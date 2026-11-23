<?php

namespace App\Services\TikTokShop;

use App\Models\Setting;
use App\Support\TikTokShopOAuthExpiry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Shop Seller OAuth (token storage + refresh), mirroring ShopeeAdminOpenApiService.
 */
class TikTokShopOAuthService
{
    public const OAUTH_SETTING_SLUG = 'tiktok_shop_oauth';

    public function isConfigured(): bool
    {
        return filled(config('tiktok_shop.app_key')) && filled(config('tiktok_shop.app_secret'));
    }

    public function hasShopAuthorization(): bool
    {
        $oauth = $this->getOAuthPayload();

        return filled($oauth['access_token'] ?? null) || filled($oauth['refresh_token'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $oauth = $this->getOAuthPayload();
        $expiresAt = TikTokShopOAuthExpiry::accessExpiresAt($oauth);

        return [
            'configured' => $this->isConfigured(),
            'has_token' => filled($oauth['access_token'] ?? null) || filled($oauth['refresh_token'] ?? null),
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_minutes' => $expiresAt ? (int) now()->diffInMinutes($expiresAt, false) : null,
            'is_expired' => TikTokShopOAuthExpiry::requiresReauthorization($oauth),
            'can_refresh' => filled($oauth['refresh_token'] ?? null),
            'last_error' => $oauth['last_error'] ?? null,
            'redirect_url' => $this->getOAuthRedirectUrl(),
        ];
    }

    public function buildAuthorizeUrl(?string $state = null): string
    {
        $base = rtrim((string) config('tiktok_shop.authorize_url'), '/');
        $serviceId = (string) config('tiktok_shop.service_id', '');

        $query = [
            'service_id' => $serviceId,
        ];

        if (filled($state)) {
            $query['state'] = $state;
        }

        return $base.'?'.http_build_query($query);
    }

    public function getOAuthRedirectUrl(): string
    {
        return (string) config('tiktok_shop.redirect_url', '');
    }

    public function getLastOAuthError(): ?string
    {
        $oauth = $this->getOAuthPayload();

        return isset($oauth['last_error']) ? (string) $oauth['last_error'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exchangeAuthCode(string $authCode): ?array
    {
        $authCode = trim($authCode);
        if ($authCode === '') {
            return null;
        }

        $response = $this->tokenGetRequest([
            'auth_code' => $authCode,
            'grant_type' => 'authorized_code',
        ]);

        $data = $this->parseTokenResponse($response, 'TikTok token exchange');
        if ($data === null) {
            return null;
        }

        $normalized = TikTokShopOAuthExpiry::normalizeTokenPayload($data);
        if (! filled($normalized['access_token'] ?? null)) {
            $this->recordOAuthError('Token exchange missing access_token');

            return null;
        }

        $this->persistOAuth(array_merge($this->getOAuthPayload(), $normalized));

        return $data;
    }

    public function refreshAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $refreshToken = $oauth['refresh_token'] ?? null;

        if (! filled($refreshToken)) {
            return null;
        }

        $response = $this->tokenRefreshRequest((string) $refreshToken);

        $data = $this->parseTokenResponse($response, 'TikTok token refresh');
        if ($data === null) {
            return null;
        }

        $normalized = TikTokShopOAuthExpiry::normalizeTokenPayload($data);
        if (! filled($normalized['access_token'] ?? null)) {
            $this->recordOAuthError('Refresh missing access_token');

            return null;
        }

        $this->persistOAuth(array_merge($oauth, $normalized, [
            'refresh_token' => $normalized['refresh_token'] ?? $refreshToken,
        ]));

        return (string) $normalized['access_token'];
    }

    public function getAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $token = $oauth['access_token'] ?? null;

        $env = trim((string) config('tiktok_shop.access_token', ''));
        if (! filled($token) && $env !== '') {
            return $env;
        }

        if ($token && ! TikTokShopOAuthExpiry::shouldRefreshAccessToken($oauth)) {
            return (string) $token;
        }

        if (filled($oauth['refresh_token'] ?? null)) {
            $refreshed = $this->refreshAccessToken();

            return $refreshed ?? ($env !== '' ? $env : null);
        }

        if (filled($token) && ! TikTokShopOAuthExpiry::requiresReauthorization($oauth)) {
            return (string) $token;
        }

        return $env !== '' ? $env : null;
    }

    /**
     * @return array{ok: bool, message: string, refreshed: bool}
     */
    public function checkConnection(): array
    {
        $oauth = $this->getOAuthPayload();

        if (! filled($oauth['refresh_token'] ?? null) && ! filled($oauth['access_token'] ?? null)) {
            return [
                'ok' => false,
                'message' => 'TikTok Shop belum diotorisasi — tidak ada access_token / refresh_token.',
                'refreshed' => false,
            ];
        }

        if (! TikTokShopOAuthExpiry::shouldRefreshAccessToken($oauth)) {
            $expiresAt = TikTokShopOAuthExpiry::accessExpiresAt($oauth);

            return [
                'ok' => true,
                'message' => 'Token TikTok Shop masih berlaku'.($expiresAt ? ' hingga '.$expiresAt->timezone(config('app.timezone'))->format('d M Y H:i') : '').'.',
                'refreshed' => false,
            ];
        }

        $token = $this->refreshAccessToken();
        if (! $token) {
            return [
                'ok' => false,
                'message' => $this->getLastOAuthError() ?? 'Gagal refresh token TikTok Shop. Authorize ulang di Aria.',
                'refreshed' => false,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Token akses TikTok Shop berhasil di-refresh.',
            'refreshed' => true,
        ];
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function tokenGetRequest(array $extra): Response
    {
        $query = array_merge([
            'app_key' => (string) config('tiktok_shop.app_key'),
            'app_secret' => (string) config('tiktok_shop.app_secret'),
        ], $extra);

        $url = rtrim((string) config('tiktok_shop.token_base_url'), '/').'/get';

        return Http::timeout(30)->acceptJson()->get($url, $query);
    }

    private function tokenRefreshRequest(string $refreshToken): Response
    {
        $url = rtrim((string) config('tiktok_shop.token_base_url'), '/').'/refresh';

        return Http::timeout(30)->acceptJson()->get($url, [
            'app_key' => (string) config('tiktok_shop.app_key'),
            'app_secret' => (string) config('tiktok_shop.app_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * @return array<string, mixed>|null Token API `data` object
     */
    private function parseTokenResponse(Response $response, string $context): ?array
    {
        if (! $response->successful()) {
            $this->recordOAuthError($context.' failed (HTTP '.$response->status().'): '.$response->body());

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->recordOAuthError($context.' returned invalid JSON');

            return null;
        }

        if ((int) ($json['code'] ?? -1) !== 0) {
            $message = trim((string) ($json['message'] ?? 'unknown'));
            $requestId = trim((string) ($json['request_id'] ?? ''));
            $detail = $message;
            if ($requestId !== '') {
                $detail .= " (request_id: {$requestId})";
            }
            $this->recordOAuthError($context.' TikTok API error: '.$detail);

            return null;
        }

        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            $this->recordOAuthError($context.' missing data object');

            return null;
        }

        $this->clearOAuthLastError();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOAuthPayload(): array
    {
        $value = Setting::getValue(self::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistOAuth(array $payload): void
    {
        Setting::query()->updateOrCreate(
            ['slug' => self::OAUTH_SETTING_SLUG],
            [
                'group' => 'tiktok_shop',
                'name' => 'TikTok Shop OAuth',
                'value' => $payload,
            ]
        );
    }

    private function recordOAuthError(string $message): void
    {
        $oauth = $this->getOAuthPayload();
        $oauth['last_error'] = $message;
        $this->persistOAuth($oauth);
        Log::error('TikTok Shop OAuth error: '.$message);
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