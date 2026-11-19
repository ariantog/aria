<?php

namespace App\Services\Shopee;

/**
 * Shopee Push Mechanism (webhook) Authorization header.
 *
 * Base string: {callbackUrl}|{rawBody} — never re-json_encode the body.
 *
 * @see https://open.shopee.com/developer-guide/18 (Push Authorization)
 */
class ShopeePushSignatureVerifier
{
    public function shouldVerify(): bool
    {
        return (bool) config('services.shopee_push.verify_signature', true);
    }

    public function partnerKey(): string
    {
        return (string) config('services.shopee_push.partner_key', '');
    }

    public function verify(string $callbackUrl, string $rawBody, ?string $authorization): bool
    {
        if (! $this->shouldVerify()) {
            return true;
        }

        $partnerKey = $this->partnerKey();
        if ($partnerKey === '') {
            return false;
        }

        $authorization = trim((string) $authorization);
        if ($authorization === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $callbackUrl.'|'.$rawBody, $partnerKey);

        return hash_equals($expected, $authorization);
    }

    /**
     * For local tests / documentation.
     */
    public function sign(string $callbackUrl, string $rawBody): string
    {
        return hash_hmac('sha256', $callbackUrl.'|'.$rawBody, $this->partnerKey());
    }

    /**
     * URL used in the signature base string — must match Push Mechanism callback URL in Console.
     */
    public function resolveCallbackUrl(\Illuminate\Http\Request $request): string
    {
        $override = trim((string) config('services.shopee_push.callback_url', ''));

        return $override !== '' ? $override : $request->url();
    }
}