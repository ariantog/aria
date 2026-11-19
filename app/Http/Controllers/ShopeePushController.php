<?php

namespace App\Http\Controllers;

use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shopee Open Platform Push Mechanism callback (test inbox only — does not post transactions).
 */
class ShopeePushController extends Controller
{
    public function __invoke(Request $request, ShopeePushSignatureVerifier $verifier): Response
    {
        $rawBody = $request->getContent();
        $authHeader = $request->header('Authorization');

        Log::info('Shopee push HTTP hit', [
            'method' => $request->method(),
            'url' => $request->url(),
            'body_bytes' => strlen($rawBody),
            'has_authorization' => $authHeader !== null && $authHeader !== '',
            'partner_key_configured' => $verifier->partnerKey() !== '',
            'verify_enabled' => $verifier->shouldVerify(),
        ]);

        $verify = $verifier->verifyWithCandidates($request, $rawBody, $authHeader);
        $callbackUrl = $verify['matched_url'] ?? $verifier->resolveCallbackUrl($request);

        if (! $verify['ok']) {
            Log::warning('Shopee push rejected — Authorization mismatch', [
                'candidate_urls' => $verify['candidate_urls'],
                'authorization_prefix' => $authHeader !== null ? substr((string) $authHeader, 0, 12) : null,
                'hint' => $verifier->partnerKey() === ''
                    ? 'Set SHOPEE_PUSH_PARTNER_KEY to Live Push Partner Key (not Open API key).'
                    : 'Set SHOPEE_PUSH_CALLBACK_URL to the exact Console callback URL if behind a proxy.',
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $shopId = isset($payload['shop_id']) ? (int) $payload['shop_id'] : null;
        $pushCode = isset($payload['code']) ? (int) $payload['code'] : null;
        $ordersn = isset($data['ordersn']) ? (string) $data['ordersn'] : null;
        $orderStatus = isset($data['status']) ? (string) $data['status'] : null;

        try {
            if (Schema::hasTable('shopee_push_inbox')) {
                ShopeePushInbox::query()->create([
                    'shop_id' => $shopId,
                    'push_code' => $pushCode,
                    'ordersn' => $ordersn,
                    'order_status' => $orderStatus,
                    'signature_ok' => true,
                    'callback_url' => $callbackUrl,
                    'payload' => $payload,
                ]);
            } else {
                Log::error('Shopee push inbox table missing — run shopee_push_inbox migration', [
                    'shop_id' => $shopId,
                    'push_code' => $pushCode,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Shopee push inbox insert failed', [
                'message' => $e->getMessage(),
                'shop_id' => $shopId,
                'push_code' => $pushCode,
            ]);
        }

        Log::info('Shopee push accepted', [
            'shop_id' => $shopId,
            'code' => $pushCode,
            'ordersn' => $ordersn,
            'status' => $orderStatus,
            'signature_url' => $callbackUrl,
        ]);

        return response('', Response::HTTP_OK);
    }
}