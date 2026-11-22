<?php

namespace App\Services\Shopee\Concerns;

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Log;

/**
 * Shop OAuth tokens for aria3 live on {@see ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG}
 * (CORENATION ADMIN). Ads and stock API clients read/sign with SHOPEE_ADMIN_*.
 */
trait UsesShopeeAdminOAuth
{
    /**
     * Legacy settings slug to migrate once when admin token is empty (upgrade path).
     */
    abstract protected function legacyOAuthSettingSlug(): string;

    /**
     * @return array<string, mixed>
     */
    protected function adminOAuthConfig(): array
    {
        return config('services.shopee_admin', []);
    }

    public function isConfigured(): bool
    {
        $config = $this->adminOAuthConfig();

        return filled($config['partner_id'] ?? null) && filled($config['partner_key'] ?? null);
    }

    public function buildAuthorizeUrl(): string
    {
        $config = $this->adminOAuthConfig();
        $redirect = (string) ($config['redirect_url'] ?? '');
        if ($redirect !== '' && stripos($redirect, 'bot') !== false) {
            Log::warning('SHOPEE_ADMIN_REDIRECT_URL contains "bot" — Shopee Open Platform rejects that redirect; use shopeecoreadmin.php');
        }

        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->signAdminPublic($path, $timestamp);

        return rtrim((string) $config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => (int) $config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

    public function getOAuthRedirectUrl(): string
    {
        return (string) ($this->adminOAuthConfig()['redirect_url'] ?? '');
    }

    protected function signAdminPublic(string $path, int $timestamp): string
    {
        $partnerId = (string) ($this->adminOAuthConfig()['partner_id'] ?? '');
        $baseString = $partnerId.$path.$timestamp;

        return hash_hmac('sha256', $baseString, (string) ($this->adminOAuthConfig()['partner_key'] ?? ''));
    }

    protected function signAdminShop(string $path, int $timestamp, string $accessToken, int $shopId): string
    {
        $partnerId = (string) ($this->adminOAuthConfig()['partner_id'] ?? '');
        $baseString = $partnerId.$path.$timestamp.$accessToken.$shopId;

        return hash_hmac('sha256', $baseString, (string) ($this->adminOAuthConfig()['partner_key'] ?? ''));
    }

    protected function adminApiBaseUrl(): string
    {
        return rtrim((string) ($this->adminOAuthConfig()['base_url'] ?? 'https://partner.shopeemobile.com'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOAuthPayload(): array
    {
        $slug = ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG;
        $value = Setting::getValue($slug, []);

        if (is_array($value) && filled($value['access_token'] ?? null)) {
            return $value;
        }

        $legacy = Setting::getValue($this->legacyOAuthSettingSlug(), []);
        if (is_array($legacy) && filled($legacy['access_token'] ?? null)) {
            $this->persistOAuth($legacy);

            return $legacy;
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persistOAuth(array $payload): void
    {
        Setting::query()->updateOrCreate(
            ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
            [
                'group' => 'shopee_admin',
                'name' => 'Shopee Admin OAuth',
                'value' => $payload,
            ]
        );
    }
}