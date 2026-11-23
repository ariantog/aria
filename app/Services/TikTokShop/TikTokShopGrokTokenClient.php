<?php

namespace App\Services\TikTokShop;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound fetch of TikTok Shop access_token from Grok bot (inverse of Shopee readonly export).
 *
 * Contract: doc/omnichannel.md — Grok TikTok token endpoint.
 */
class TikTokShopGrokTokenClient
{
    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && filled(config('tiktok_shop.grok_token.url'))
            && filled(config('tiktok_shop.grok_token.bearer_token'));
    }

    public function isEnabled(): bool
    {
        return filter_var(config('tiktok_shop.grok_token.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>|null Normalized payload: access_token, expires_at?, shop_cipher?
     */
    public function fetchToken(?string $shopRef = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $url = (string) config('tiktok_shop.grok_token.url');
        $query = [];
        if (filled($shopRef)) {
            $query['shop_ref'] = trim($shopRef);
        }

        try {
            /** @var Response $response */
            $response = Http::timeout((int) config('tiktok_shop.grok_token.timeout_seconds', 15))
                ->acceptJson()
                ->withToken((string) config('tiktok_shop.grok_token.bearer_token'))
                ->get($url, $query);
        } catch (\Throwable $e) {
            Log::warning('TikTok Grok token fetch failed', [
                'message' => $e->getMessage(),
                'shop_ref' => $shopRef,
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('TikTok Grok token fetch HTTP error', [
                'status' => $response->status(),
                'shop_ref' => $shopRef,
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::warning('TikTok Grok token fetch returned invalid JSON');

            return null;
        }

        $accessToken = trim((string) ($json['access_token'] ?? ''));
        if ($accessToken === '') {
            Log::warning('TikTok Grok token fetch missing access_token', [
                'error' => $json['error'] ?? null,
            ]);

            return null;
        }

        $normalized = [
            'access_token' => $accessToken,
            'fetched_at' => now()->toIso8601String(),
            'source' => 'grok',
        ];

        if (filled($json['expires_at'] ?? null)) {
            $normalized['expires_at'] = (string) $json['expires_at'];
        } elseif (isset($json['expires_at_unix']) && (int) $json['expires_at_unix'] > 0) {
            $normalized['expires_at'] = now()->setTimestamp((int) $json['expires_at_unix'])->toIso8601String();
        } elseif (isset($json['expires_in']) && (int) $json['expires_in'] > 0) {
            $normalized['expires_at'] = now()->addSeconds((int) $json['expires_in'])->toIso8601String();
        }

        if (filled($json['shop_cipher'] ?? null)) {
            $normalized['shop_cipher_hint'] = trim((string) $json['shop_cipher']);
        }

        return $normalized;
    }
}