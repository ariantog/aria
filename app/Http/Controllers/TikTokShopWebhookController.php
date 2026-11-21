<?php

namespace App\Http\Controllers;

use App\Jobs\PersistTikTokShopWebhookInboxJob;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Shop Event API webhook (verify + optional inbox; does not post transactions).
 */
class TikTokShopWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TikTokShopWebhookSignatureVerifier $verifier,
    ): Response {
        if (! config('tiktok_shop.webhook.enabled', true)) {
            Log::info('TikTok Shop webhook hit while disabled (TIKTOK_SHOP_WEBHOOK_ENABLED=false)');

            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $rawBody = $request->getContent();
        $authHeader = $request->header('Authorization');

        if (! $verifier->verify($rawBody, $authHeader)) {
            Log::warning('TikTok Shop webhook rejected — Authorization mismatch', [
                'hint' => $verifier->appSecret() === ''
                    ? 'Set TIKTOK_SHOP_APP_KEY and TIKTOK_SHOP_APP_SECRET.'
                    : 'Signature is HMAC-SHA256(app_key + raw_body, app_secret) hex in Authorization.',
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $orderId = isset($data['order_id']) ? (string) $data['order_id'] : null;
        $eventType = isset($payload['type']) ? (int) $payload['type'] : null;

        Log::info('TikTok Shop webhook accepted', [
            'type' => $eventType,
            'tts_notification_id' => $payload['tts_notification_id'] ?? null,
            'order_id' => $orderId,
        ]);

        PersistTikTokShopWebhookInboxJob::dispatchAfterResponse($payload, true);

        return response('', Response::HTTP_OK);
    }
}