<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Cached TikTok Shop access tokens (settings slug tiktok_shop_oauth or Grok fetch).
 */
final class TikTokShopOAuthExpiry
{
    public const REFRESH_BUFFER_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function accessExpiresAt(array $payload): ?Carbon
    {
        if (! isset($payload['expires_at'])) {
            return null;
        }

        try {
            return Carbon::parse((string) $payload['expires_at']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function shouldRefreshCachedToken(array $payload, int $bufferSeconds = self::REFRESH_BUFFER_SECONDS): bool
    {
        if (! filled($payload['access_token'] ?? null)) {
            return true;
        }

        $expiresAt = self::accessExpiresAt($payload);

        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt->lte(now()->addSeconds($bufferSeconds));
    }
}