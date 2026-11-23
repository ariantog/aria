<?php

namespace App\Services\TikTokShop;

use App\Models\Setting;
use App\Support\TikTokShopOAuthExpiry;

/**
 * Resolves TikTok Shop access_token: settings cache → Grok HTTP → env TIKTOK_SHOP_ACCESS_TOKEN.
 */
class TikTokShopAccessTokenResolver
{
    public const OAUTH_SETTING_SLUG = 'tiktok_shop_oauth';

    public function __construct(
        protected TikTokShopGrokTokenClient $grokClient,
    ) {}

    public function grokFetchEnabled(): bool
    {
        return $this->grokClient->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $global = $this->getGlobalCachedPayload();

        return [
            'grok_fetch_enabled' => $this->grokFetchEnabled(),
            'grok_token_url' => filled(config('tiktok_shop.grok_token.url'))
                ? (string) config('tiktok_shop.grok_token.url')
                : null,
            'has_cached_token' => filled($global['access_token'] ?? null),
            'expires_at' => TikTokShopOAuthExpiry::accessExpiresAt($global)?->toIso8601String(),
            'env_fallback' => filled(config('tiktok_shop.access_token', '')),
            'last_error' => $global['last_error'] ?? null,
        ];
    }

    public function resolveAccessToken(?string $shopRef = null): ?string
    {
        $cached = $this->getCachedPayloadForShop($shopRef);
        if (filled($cached['access_token'] ?? null)
            && ! TikTokShopOAuthExpiry::shouldRefreshCachedToken($cached)) {
            return (string) $cached['access_token'];
        }

        if ($this->grokFetchEnabled()) {
            $fetched = $this->grokClient->fetchToken($shopRef);
            if ($fetched !== null && filled($fetched['access_token'] ?? null)) {
                $this->persistCachedPayload($fetched, $shopRef);

                return (string) $fetched['access_token'];
            }

            $this->recordLastError('Grok token fetch failed or returned no access_token', $shopRef);
        }

        if (filled($cached['access_token'] ?? null)) {
            return (string) $cached['access_token'];
        }

        $env = trim((string) config('tiktok_shop.access_token', ''));

        return $env !== '' ? $env : null;
    }

    public function hasAccessToken(?string $shopRef = null): bool
    {
        return filled($this->resolveAccessToken($shopRef));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getGlobalCachedPayload(): array
    {
        $value = Setting::getValue(self::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCachedPayloadForShop(?string $shopRef): array
    {
        $global = $this->getGlobalCachedPayload();
        if (! filled($shopRef)) {
            return $global;
        }

        $shopRef = trim($shopRef);
        $byShop = $global['by_shop_ref'] ?? [];
        if (! is_array($byShop)) {
            return $global;
        }

        $shopPayload = $byShop[$shopRef] ?? null;

        return is_array($shopPayload) ? array_merge($global, $shopPayload) : $global;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persistCachedPayload(array $payload, ?string $shopRef): void
    {
        unset($payload['last_error']);
        $global = $this->getGlobalCachedPayload();

        if (filled($shopRef)) {
            $shopRef = trim($shopRef);
            $byShop = is_array($global['by_shop_ref'] ?? null) ? $global['by_shop_ref'] : [];
            $byShop[$shopRef] = array_merge($byShop[$shopRef] ?? [], $payload);
            $global['by_shop_ref'] = $byShop;
        } else {
            $global = array_merge($global, $payload);
        }

        Setting::query()->updateOrCreate(
            ['slug' => self::OAUTH_SETTING_SLUG],
            [
                'group' => 'tiktok_shop',
                'name' => 'TikTok Shop OAuth (Grok cache)',
                'value' => $global,
            ]
        );
    }

    protected function recordLastError(string $message, ?string $shopRef): void
    {
        $global = $this->getGlobalCachedPayload();
        $global['last_error'] = $message;
        if (filled($shopRef)) {
            $global['last_error_shop_ref'] = trim($shopRef);
        }

        Setting::query()->updateOrCreate(
            ['slug' => self::OAUTH_SETTING_SLUG],
            [
                'group' => 'tiktok_shop',
                'name' => 'TikTok Shop OAuth (Grok cache)',
                'value' => $global,
            ]
        );
    }
}