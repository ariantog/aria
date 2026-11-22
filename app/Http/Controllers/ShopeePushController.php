<?php

namespace App\Http\Controllers;

use App\Models\ShopeePushInbox;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
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
        MarketplaceWebhookRecorder $webhookRecorder,
        MarketplaceWebhookStatusPresenter $statusPresenter,
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
        $orderStatus = $statusPresenter->resolveIngestStatus(
            MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
            $pushCode,
            $payload,
        );

        $shadowStubUpserted = false;

        // Read-only shadow compare (new table only). Jubelio webhooks/cron unchanged.
        if (config('services.shopee_push.shadow_enabled') && $pushCode === 3 && filled($ordersn)) {
            try {
                if (Schema::hasTable('marketplace_order_shadows')) {
                    $shadowRecorder->upsertStubFromPush($shopId, $ordersn, $orderStatus, $pushCode, $data, $payload);
                    $shadowStubUpserted = true;
                }
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
            'shadow_stub_upserted' => $shadowStubUpserted,
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

                    $webhookRecorder->recordShopee(
                        $payload,
                        true,
                        $shopId,
                        $pushCode,
                        $ordersn,
                        $orderStatus,
                    );
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