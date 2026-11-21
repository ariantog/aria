<?php

namespace App\Services\TikTokShop;

/**
 * TikTok Shop webhook Authorization header (inbound only — not Open API request sign).
 *
 * signature = hex(HMAC-SHA256(key = app_secret, message = app_key + raw_body))
 *
 * @see https://partner.tiktokshop.com/docv2/page/tts-webhooks-overview
 */
class TikTokShopWebhookSignatureVerifier
{
    public function shouldVerify(): bool
    {
        return (bool) config('tiktok_shop.webhook.verify_signature', true);
    }

    public function appKey(): string
    {
        return (string) config('tiktok_shop.app_key', '');
    }

    public function appSecret(): string
    {
        return (string) config('tiktok_shop.app_secret', '');
    }

    public function verify(string $rawBody, ?string $authorization): bool
    {
        if (! $this->shouldVerify()) {
            return true;
        }

        $appKey = $this->appKey();
        $appSecret = $this->appSecret();
        if ($appKey === '' || $appSecret === '') {
            return false;
        }

        $authorization = trim((string) $authorization);
        if ($authorization === '') {
            return false;
        }

        $expected = $this->sign($rawBody);

        return hash_equals($expected, $authorization);
    }

    /**
     * For tests / local curl examples.
     */
    public function sign(string $rawBody): string
    {
        $message = $this->appKey().$rawBody;

        return hash_hmac('sha256', $message, $this->appSecret());
    }
}