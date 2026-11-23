<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Shared Shopee shop OAuth expiry helpers (settings row shopee_admin_oauth).
 */
final class ShopeeOAuthExpiry
{
    /** Refresh access token this many seconds before expires_at (Shopee TTL ≈ 4h). */
    public const REFRESH_BUFFER_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function expireInSecondsFromPayload(array $payload): int
    {
        foreach (['expire_in', 'expires_in'] as $key) {
            if (isset($payload[$key]) && (int) $payload[$key] > 0) {
                return (int) $payload[$key];
            }
        }

        return 14_400;
    }

    /**
     * @param  array<string, mixed>  $oauth  Stored settings payload
     */
    public static function accessExpiresAt(array $oauth): ?Carbon
    {
        if (! isset($oauth['expires_at'])) {
            return null;
        }

        return Carbon::parse($oauth['expires_at']);
    }

    /**
     * True when the seller must run the browser OAuth flow again (no usable refresh path).
     *
     * @param  array<string, mixed>  $oauth
     */
    public static function requiresReauthorization(array $oauth): bool
    {
        if (! filled($oauth['access_token'] ?? null) && ! filled($oauth['refresh_token'] ?? null)) {
            return true;
        }

        if (! filled($oauth['refresh_token'] ?? null)) {
            $expiresAt = self::accessExpiresAt($oauth);

            return $expiresAt === null || $expiresAt->isPast();
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $oauth
     */
    public static function shouldRefreshAccessToken(array $oauth, int $bufferSeconds = self::REFRESH_BUFFER_SECONDS): bool
    {
        if (! filled($oauth['refresh_token'] ?? null)) {
            return false;
        }

        if ((int) ($oauth['shop_id'] ?? 0) <= 0) {
            return false;
        }

        if (! filled($oauth['access_token'] ?? null)) {
            return true;
        }

        $expiresAt = self::accessExpiresAt($oauth);

        if ($expiresAt === null) {
            return true;
        }

        return $expiresAt->lte(now()->addSeconds($bufferSeconds));
    }
}