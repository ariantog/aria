<?php

namespace App\Http\Controllers;

use App\Jobs\PersistShopeePushInboxJob;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use App\Support\MarketplaceWebhookRelayBearer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * CDN edge ACK-first forward: bearer (optional) + platform Push HMAC, then same persist job as /shopee/push.
 */
class ShopeePushRelayController extends Controller
{
    public function __invoke(
        Request $request,
        ShopeePushSignatureVerifier $verifier,
    ): Response {
        if (! config('services.shopee_push.relay.enabled', false)) {
            return response('', Response::HTTP_NOT_FOUND);
        }

        $relayBearer = (string) config('services.shopee_push.relay.bearer', '');
        if (! MarketplaceWebhookRelayBearer::accepts($request, $relayBearer)) {
            Log::warning('Shopee push relay rejected — bearer mismatch');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $rawBody = $request->getContent();
        $platformAuthorization = $this->resolvePlatformAuthorization($request);
        $callbackUrl = $this->resolveCallbackUrl($request);

        if (! $this->verifyRelaySignature($verifier, $callbackUrl, $rawBody, $platformAuthorization)) {
            Log::warning('Shopee push relay rejected — platform Authorization mismatch', [
                'callback_url' => $callbackUrl !== '' ? $callbackUrl : null,
                'hint' => $verifier->partnerKey() === ''
                    ? 'Set SHOPEE_PUSH_PARTNER_KEY to Live Push Partner Key.'
                    : 'Forward X-Shopee-Push-Authorization and X-Shopee-Push-Callback-Url from CDN; SHOPEE_PUSH_CALLBACK_URL must match Console.',
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $pushCode = isset($payload['code']) ? (int) $payload['code'] : null;
        $ordersn = $this->resolveOrderSn($data);

        Log::info('Shopee push relay accepted', [
            'code' => $pushCode,
            'ordersn' => $ordersn,
            'callback_url' => $callbackUrl,
        ]);

        PersistShopeePushInboxJob::dispatchAfterResponse($payload, true, $callbackUrl !== '' ? $callbackUrl : null);

        return response('', Response::HTTP_OK);
    }

    private function resolvePlatformAuthorization(Request $request): ?string
    {
        $forwarded = trim((string) $request->header('X-Shopee-Push-Authorization'));
        if ($forwarded !== '') {
            return $forwarded;
        }

        $authorization = trim((string) $request->header('Authorization'));
        if ($authorization !== '' && ! preg_match('/^Bearer\s+/i', $authorization)) {
            return $authorization;
        }

        return null;
    }

    private function resolveCallbackUrl(Request $request): string
    {
        $fromHeader = trim((string) $request->header('X-Shopee-Push-Callback-Url'));
        if ($fromHeader !== '') {
            return $fromHeader;
        }

        return trim((string) config('services.shopee_push.callback_url', ''));
    }

    private function verifyRelaySignature(
        ShopeePushSignatureVerifier $verifier,
        string $callbackUrl,
        string $rawBody,
        ?string $platformAuthorization,
    ): bool {
        if (! $verifier->shouldVerify()) {
            return true;
        }

        if ($callbackUrl === '') {
            return false;
        }

        return $verifier->verify($callbackUrl, $rawBody, $platformAuthorization);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOrderSn(array $data): ?string
    {
        if (isset($data['ordersn']) && $data['ordersn'] !== '') {
            return (string) $data['ordersn'];
        }

        if (isset($data['order_sn']) && $data['order_sn'] !== '') {
            return (string) $data['order_sn'];
        }

        return null;
    }
}