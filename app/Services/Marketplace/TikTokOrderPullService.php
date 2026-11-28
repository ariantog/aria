<?php

namespace App\Services\Marketplace;

use App\Models\TikTokShopWebhookInbox;
use App\Models\TiktokShopSync;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fetch one TikTok Shop / Tokopedia order via Open API and persist like a webhook ingest.
 */
class TikTokOrderPullService
{
    private const PULL_EVENT_TYPE = 1;

    public function __construct(
        private TikTokShopOpenApiService $openApi,
        private MarketplaceWebhookRecorder $webhookRecorder,
        private TikTokShopWebhookOnOrderApplicator $onOrderApplicator,
        private MarketplaceTikTokCommercePlatformEnricher $commercePlatformEnricher,
    ) {}

    /**
     * @return array{
     *   shop_ref: string,
     *   order_ref: string,
     *   order_status: string|null,
     *   ingested: bool,
     *   webhook_event: bool,
     *   inbox_event: bool,
     *   commerce_platform: string|null,
     *   error: string|null,
     * }
     */
    public function pullAndIngestOrder(string $shopRef, string $orderRef): array
    {
        $shopRef = trim($shopRef);
        $orderRef = trim($orderRef);
        $result = [
            'shop_ref' => $shopRef,
            'order_ref' => $orderRef,
            'order_status' => null,
            'ingested' => false,
            'webhook_event' => false,
            'inbox_event' => false,
            'commerce_platform' => null,
            'error' => null,
        ];

        if ($shopRef === '' || $orderRef === '') {
            $result['error'] = 'shop_ref dan order_ref wajib.';

            return $result;
        }

        $shopCipher = $this->resolveShopCipher($shopRef);
        if (! $this->openApi->isMock() && ! $this->openApi->liveCredentialsReady($shopCipher, $shopRef)) {
            $result['error'] = 'Kredensial TikTok Shop tidak lengkap (access_token / shop_cipher).';

            return $result;
        }

        $detailResponse = $this->openApi->getOrderDetail($orderRef, $shopCipher, $shopRef);
        if ($detailResponse === null) {
            $result['error'] = 'Get Order Detail gagal — cek order_ref / shop_ref atau log API.';

            return $result;
        }

        $orderRow = $this->openApi->extractFirstOrder($detailResponse);
        if ($orderRow === null) {
            $result['error'] = 'Order tidak ditemukan di respons TikTok Shop.';

            return $result;
        }

        $status = isset($orderRow['order_status']) ? trim((string) $orderRow['order_status']) : '';
        if ($status === '' && isset($orderRow['status'])) {
            $status = trim((string) $orderRow['status']);
        }
        if ($status === '') {
            $status = 'UNKNOWN';
        }

        $updateTime = $orderRow['update_time'] ?? $orderRow['updated_at'] ?? time();
        if (is_numeric($updateTime)) {
            $updateTime = (int) $updateTime;
        } else {
            $updateTime = time();
        }

        $notificationId = sprintf('api-pull:%s:%s', $orderRef, $updateTime);

        $payload = [
            'type' => self::PULL_EVENT_TYPE,
            'tts_notification_id' => $notificationId,
            'shop_id' => $shopRef,
            'data' => [
                'order_id' => $orderRef,
                'order_status' => $status,
                'update_time' => $updateTime,
                'order_detail' => $orderRow,
            ],
            'source' => 'tiktok_pull',
        ];

        try {
            if ($this->persistLegacyInbox($payload, $notificationId)) {
                $result['inbox_event'] = true;
            }

            if (config('marketplace.webhook.unified_storage', false)) {
                $this->webhookRecorder->recordTikTok($payload, true);
                $result['webhook_event'] = true;
            }

            $this->onOrderApplicator->applyFromWebhook($payload);

            $platform = $this->openApi->extractCommercePlatform($detailResponse);
            if (filled($platform)) {
                $this->commercePlatformEnricher->persistCommercePlatform(
                    MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
                    $orderRef,
                    $platform,
                    null,
                );
                $result['commerce_platform'] = strtoupper((string) $platform);
            } else {
                $enriched = $this->commercePlatformEnricher->enrichOrder($orderRef);
                if (filled($enriched)) {
                    $result['commerce_platform'] = strtoupper($enriched);
                }
            }
        } catch (Throwable $e) {
            Log::warning('TikTok order pull ingest failed', [
                'order_ref' => $orderRef,
                'shop_ref' => $shopRef,
                'message' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();

            return $result;
        }

        $result['order_status'] = $status;
        $result['ingested'] = $result['webhook_event'] || $result['inbox_event'];

        return $result;
    }

    protected function resolveShopCipher(string $shopRef): ?string
    {
        if (Schema::hasTable('tiktok_shop_syncs')) {
            $cipher = TiktokShopSync::query()
                ->where('shop_ref', $shopRef)
                ->value('shop_cipher');
            if (filled($cipher)) {
                return trim((string) $cipher);
            }
        }

        $fallback = trim((string) config('tiktok_shop.shop_cipher', ''));

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persistLegacyInbox(array $payload, string $notificationId): bool
    {
        if (! config('tiktok_shop.webhook.store_inbox', true)) {
            return false;
        }

        if (! Schema::hasTable('tiktok_shop_webhook_inbox')) {
            return false;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $row = TikTokShopWebhookInbox::query()->firstOrCreate(
            ['tts_notification_id' => $notificationId],
            [
                'event_type' => isset($payload['type']) ? (int) $payload['type'] : null,
                'shop_id' => isset($payload['shop_id']) ? (string) $payload['shop_id'] : null,
                'order_id' => isset($data['order_id']) ? (string) $data['order_id'] : null,
                'order_status' => isset($data['order_status']) ? (string) $data['order_status'] : null,
                'signature_ok' => true,
                'payload' => $payload,
            ],
        );

        return $row->wasRecentlyCreated;
    }
}