<?php

namespace App\Support;

/**
 * Multi-shop OAuth payload stored in settings slug {@see \App\Services\Shopee\ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG}.
 *
 * Legacy rows are a single flat shop (shop_id + tokens at top level). New authorizations
 * merge into {@see self::SHOPS_KEY} so Indonesia + FLEXI/Malaysia export shops can coexist.
 */
final class ShopeeAdminOAuthStore
{
    public const SHOPS_KEY = 'shops';

    /**
     * @param  array<string, mixed>  $raw
     * @return list<int>
     */
    public static function authorizedShopIds(array $raw): array
    {
        $ids = [];

        foreach (self::shopsMap($raw) as $key => $shop) {
            $id = (int) $key;
            if ($id > 0 && (filled($shop['access_token'] ?? null) || filled($shop['refresh_token'] ?? null))) {
                $ids[] = $id;
            }
        }

        $primary = self::primaryShopId($raw);
        if ($primary > 0 && ! in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    public static function primaryShopId(array $raw): int
    {
        $configured = (int) config('services.shopee_admin.primary_shop_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        return (int) ($raw['shop_id'] ?? 0);
    }

    /**
     * Credentials for one shop API call (tokens + shop_id).
     *
     * @param  array<string, mixed>  $raw  Full settings value
     * @return array<string, mixed>
     */
    public static function credentialsForShop(array $raw, int $shopId): array
    {
        if ($shopId <= 0) {
            $shopId = self::primaryShopId($raw);
        }

        $shops = self::shopsMap($raw);
        if (isset($shops[(string) $shopId]) && is_array($shops[(string) $shopId])) {
            return array_merge($shops[(string) $shopId], ['shop_id' => $shopId]);
        }

        if ((int) ($raw['shop_id'] ?? 0) === $shopId) {
            return $raw;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $shopTokens  access_token, refresh_token, expires_at, …
     * @return array<string, mixed>
     */
    public static function mergeShopAuthorization(array $raw, int $shopId, array $shopTokens): array
    {
        $shops = self::shopsMap($raw);
        $shops[(string) $shopId] = array_merge(
            is_array($shops[(string) $shopId] ?? null) ? $shops[(string) $shopId] : [],
            $shopTokens,
            ['shop_id' => $shopId],
        );

        $raw[self::SHOPS_KEY] = $shops;

        $primary = self::primaryShopId($raw);
        $mirrorPrimary = $primary <= 0 || $shopId === $primary;

        if ($mirrorPrimary) {
            $raw['shop_id'] = $shopId;
            $raw['access_token'] = $shopTokens['access_token'] ?? null;
            $raw['refresh_token'] = $shopTokens['refresh_token'] ?? null;
            $raw['expires_at'] = $shopTokens['expires_at'] ?? null;
            $raw['last_refreshed_at'] = $shopTokens['last_refreshed_at'] ?? null;
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $shopTokens
     * @return array<string, mixed>
     */
    public static function mergeShopTokensAfterRefresh(array $raw, int $shopId, array $shopTokens): array
    {
        return self::mergeShopAuthorization($raw, $shopId, $shopTokens);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array<string, mixed>>
     */
    public static function shopsMap(array $raw): array
    {
        $map = [];

        if (isset($raw[self::SHOPS_KEY]) && is_array($raw[self::SHOPS_KEY])) {
            foreach ($raw[self::SHOPS_KEY] as $key => $shop) {
                if (is_array($shop)) {
                    $map[(string) $key] = $shop;
                }
            }
        }

        $legacyShopId = (int) ($raw['shop_id'] ?? 0);
        if ($legacyShopId > 0 && ! isset($map[(string) $legacyShopId])) {
            $map[(string) $legacyShopId] = [
                'access_token' => $raw['access_token'] ?? null,
                'refresh_token' => $raw['refresh_token'] ?? null,
                'expires_at' => $raw['expires_at'] ?? null,
                'last_refreshed_at' => $raw['last_refreshed_at'] ?? null,
                'shop_id' => $legacyShopId,
            ];
        }

        return $map;
    }
}