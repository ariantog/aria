<?php

namespace App\Http\Controllers;

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shopee Open Platform Push Mechanism callback (verify + optional inbox; does not post transactions).
 */
class ShopeePushController extends Controller
{
    public function __invoke(Request $request, ShopeePushSignatureVerifier $verifier): Response
    {
        $rawBody = $request->getContent();
        $authHeader = $request->header('Authorization');

        $verify = $verifier->verifyWithCandidates($request, $rawBody, $authHeader);
        $callbackUrl = $verify['matched_url'] ?? $verifier->resolveCallbackUrl($request);

        if (! $verify['ok']) {
            Log::warning('Shopee push rejected — Authorization mismatch', [
                'candidate_urls' => $verify['candidate_urls'],
                'hint' => $verifier->partnerKey() === ''
                    ? 'Set SHOPEE_PUSH_PARTNER_KEY to Live Push Partner Key.'
                    : 'Set SHOPEE_PUSH_CALLBACK_URL to the exact Console callback URL.',
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

        // Read-only shadow compare (new table only). Jubelio webhooks/cron unchanged.
        if (config('services.shopee_push.shadow_enabled') && $pushCode === 3 && filled($ordersn)) {
            RecordShopeePushShadowJob::dispatch($shopId, $ordersn, $orderStatus, $pushCode);
        }

        if ($this->shouldPersistInbox($pushCode)) {
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
                }
            } catch (Throwable $e) {
                Log::error('Shopee push inbox insert failed', [
                    'message' => $e->getMessage(),
                    'push_code' => $pushCode,
                    'ordersn' => $ordersn,
                ]);
            }
        }

        return response('', Response::HTTP_OK);
    }

    private function shouldPersistInbox(?int $pushCode): bool
    {
        if (! config('services.shopee_push.store_inbox')) {
            return false;
        }

        if ($pushCode === null) {
            return false;
        }

        $allowed = config('services.shopee_push.store_inbox_codes', [3, 4]);

        return in_array($pushCode, $allowed, true);
    }
}