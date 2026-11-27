<?php

namespace App\Http\Controllers;

use App\Jobs\PersistTikTokShopWebhookInboxJob;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;
use App\Support\MarketplaceWebhookRelayBearer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * CDN edge ACK-first forward: bearer (optional) + TikTok HMAC, then same persist job as /tiktok-shop/webhook.
 */
class TikTokShopWebhookRelayController extends Controller
{
    public function __invoke(
        Request $request,
        TikTokShopWebhookSignatureVerifier $verifier,
    ): Response {
        if (! config('tiktok_shop.webhook.relay.enabled', false)) {
            return response('', Response::HTTP_NOT_FOUND);
        }

        if (! config('tiktok_shop.webhook.enabled', true)) {
            Log::info('TikTok Shop webhook relay hit while webhooks disabled (TIKTOK_SHOP_WEBHOOK_ENABLED=false)');

            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $relayBearer = (string) config('tiktok_shop.webhook.relay.bearer', '');
        if (! MarketplaceWebhookRelayBearer::accepts($request, $relayBearer)) {
            Log::warning('TikTok Shop webhook relay rejected — bearer mismatch');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $rawBody = $request->getContent();
        $platformAuthorization = $this->resolvePlatformAuthorization($request);

        if (! $verifier->verify($rawBody, $platformAuthorization)) {
            Log::warning('TikTok Shop webhook relay rejected — platform Authorization mismatch', [
                'hint' => $verifier->appSecret() === ''
                    ? 'Set TIKTOK_SHOP_APP_KEY and TIKTOK_SHOP_APP_SECRET.'
                    : 'Forward X-TikTok-Shop-Authorization from CDN; signature is HMAC-SHA256(app_key + raw_body, app_secret) hex.',
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $orderId = isset($data['order_id']) ? (string) $data['order_id'] : null;
        $eventType = isset($payload['type']) ? (int) $payload['type'] : null;

        Log::info('TikTok Shop webhook relay accepted', [
            'type' => $eventType,
            'tts_notification_id' => $payload['tts_notification_id'] ?? null,
            'order_id' => $orderId,
        ]);

        PersistTikTokShopWebhookInboxJob::dispatchAfterResponse($payload, true);

        return response('', Response::HTTP_OK);
    }

    private function resolvePlatformAuthorization(Request $request): ?string
    {
        $forwarded = trim((string) $request->header('X-TikTok-Shop-Authorization'));
        if ($forwarded !== '') {
            return $forwarded;
        }

        $authorization = trim((string) $request->header('Authorization'));
        if ($authorization !== '' && ! preg_match('/^Bearer\s+/i', $authorization)) {
            return $authorization;
        }

        return null;
    }
}