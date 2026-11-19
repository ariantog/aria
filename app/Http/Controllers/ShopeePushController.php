<?php

namespace App\Http\Controllers;

use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Shopee Open Platform Push Mechanism callback (test inbox only — does not post transactions).
 */
class ShopeePushController extends Controller
{
    public function __invoke(Request $request, ShopeePushSignatureVerifier $verifier): Response
    {
        $rawBody = $request->getContent();
        $callbackUrl = $verifier->resolveCallbackUrl($request);
        $authorization = $request->header('Authorization');
        $signatureOk = $verifier->verify($callbackUrl, $rawBody, $authorization);

        if (! $signatureOk) {
            Log::warning('Shopee push rejected — Authorization mismatch', [
                'callback_url' => $callbackUrl,
                'has_authorization' => $authorization !== null && $authorization !== '',
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

        ShopeePushInbox::query()->create([
            'shop_id' => $shopId,
            'push_code' => $pushCode,
            'ordersn' => $ordersn,
            'order_status' => $orderStatus,
            'signature_ok' => true,
            'callback_url' => $callbackUrl,
            'payload' => $payload,
        ]);

        Log::info('Shopee push stored (inbox only)', [
            'shop_id' => $shopId,
            'code' => $pushCode,
            'ordersn' => $ordersn,
            'status' => $orderStatus,
        ]);

        return response('', Response::HTTP_OK);
    }
}