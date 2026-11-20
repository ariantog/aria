<?php

namespace App\Http\Controllers;

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\ShopeePushInbox;
use App\Services\Marketplace\ShopeeOrderShadowRecorder;
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
    public function __invoke(
        Request $request,
        ShopeePushSignatureVerifier $verifier,
        ShopeeOrderShadowRecorder $shadowRecorder,
    ): Response
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
        $ordersn = $this->resolveOrderSn($data);
        $orderStatus = isset($data['status']) ? (string) $data['status'] : null;

        $shadowDispatched = false;

        // Read-only shadow compare (new table only). Jubelio webhooks/cron unchanged.
        if (config('services.shopee_push.shadow_enabled') && $pushCode === 3 && filled($ordersn)) {
            try {
                if (Schema::hasTable('marketplace_order_shadows')) {
                    $shadowRecorder->upsertStubFromPush($shopId, $ordersn, $orderStatus, $pushCode, $data, $payload);
                }
                RecordShopeePushShadowJob::dispatch($shopId, $ordersn, $orderStatus, $pushCode);
                $shadowDispatched = true;
            } catch (Throwable $e) {
                Log::warning('Shopee push shadow stub failed', [
                    'ordersn' => $ordersn,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Shopee push accepted', [
            'code' => $pushCode,
            'ordersn' => $ordersn,
            'shadow_dispatched' => $shadowDispatched,
        ]);

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