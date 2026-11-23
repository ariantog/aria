<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Stored TikTok Shop OAuth payload helpers (settings slug tiktok_shop_oauth).
 */
final class TikTokShopOAuthExpiry
{
    public const REFRESH_BUFFER_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $payload  TikTok token API data or stored settings row
     */
    public static function accessExpiresAt(array $payload): ?Carbon
    {
        if (isset($payload['expires_at'])) {
            try {
                return Carbon::parse((string) $payload['expires_at']);
            } catch (\Throwable) {
                return null;
            }
        }

        if (isset($payload['access_token_expire_in']) && (int) $payload['access_token_expire_in'] > 0) {
            return Carbon::createFromTimestamp((int) $payload['access_token_expire_in']);
        }

        return null;
    }

    /**
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

        if (isset($oauth['refresh_token_expire_in']) && (int) $oauth['refresh_token_expire_in'] > 0) {
            return Carbon::createFromTimestamp((int) $oauth['refresh_token_expire_in'])->isPast();
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

        if (! filled($oauth['access_token'] ?? null)) {
            return true;
        }

        $expiresAt = self::accessExpiresAt($oauth);

        if ($expiresAt === null) {
            return true;
        }

        return $expiresAt->lte(now()->addSeconds($bufferSeconds));
    }

    /**
     * @param  array<string, mixed>  $data  TikTok token API `data` object
     * @return array<string, mixed>
     */
    public static function normalizeTokenPayload(array $data): array
    {
        $payload = [
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'last_error' => null,
            'last_refreshed_at' => now()->toIso8601String(),
        ];

        if (isset($data['access_token_expire_in']) && (int) $data['access_token_expire_in'] > 0) {
            $payload['access_token_expire_in'] = (int) $data['access_token_expire_in'];
            $payload['expires_at'] = Carbon::createFromTimestamp((int) $data['access_token_expire_in'])->toIso8601String();
        }

        if (isset($data['refresh_token_expire_in']) && (int) $data['refresh_token_expire_in'] > 0) {
            $payload['refresh_token_expire_in'] = (int) $data['refresh_token_expire_in'];
        }

        foreach (['open_id', 'seller_name', 'seller_base_region'] as $key) {
            if (filled($data[$key] ?? null)) {
                $payload[$key] = $data[$key];
            }
        }

        return $payload;
    }
}