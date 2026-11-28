<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\TiktokShopSync;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Maintainer entry: pull one marketplace order by ref into unified webhook storage.
 */
class MarketplaceOrderRefPullService
{
    public function __construct(
        private ShopeeOrderPollService $shopeePull,
        private TikTokOrderPullService $tikTokPull,
        private OmnichannelPostReturnEligibility $returnEligibility,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   channel: string,
     *   order_ref: string,
     *   order_status: string|null,
     *   shop_ref: string|null,
     *   commerce_platform: string|null,
     *   webhook_event: bool,
     *   shadow: bool,
     *   jubelio_placeholder: bool,
     *   inbox_event: bool,
     *   return_signal: bool,
     *   unified_storage_active: bool,
     *   warning: string|null,
     *   error: string|null,
     * }
     */
    public function pull(string $channel, string $orderRef, ?string $shopRef = null): array
    {
        $channel = strtolower(trim($channel));
        $orderRef = trim($orderRef);
        $shopRef = $shopRef !== null ? trim($shopRef) : null;

        $base = [
            'ok' => false,
            'channel' => $channel,
            'order_ref' => $orderRef,
            'order_status' => null,
            'shop_ref' => $shopRef,
            'commerce_platform' => null,
            'webhook_event' => false,
            'shadow' => false,
            'jubelio_placeholder' => false,
            'inbox_event' => false,
            'return_signal' => false,
            'unified_storage_active' => (bool) config('marketplace.webhook.unified_storage', false),
            'warning' => null,
            'error' => null,
        ];

        if ($orderRef === '') {
            return array_merge($base, ['error' => 'Order ref wajib diisi.']);
        }

        if (! in_array($channel, [MarketplaceWebhookRecorder::CHANNEL_SHOPEE, MarketplaceWebhookRecorder::CHANNEL_TIKTOK, 'tokopedia'], true)) {
            return array_merge($base, ['error' => 'Channel tidak didukung — pilih Shopee atau TikTok/Tokopedia.']);
        }

        if ($channel === 'tokopedia') {
            $channel = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;
            $base['channel'] = $channel;
        }

        if (! $base['unified_storage_active']) {
            $base['warning'] = 'MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=false — baris tidak masuk marketplace_webhook_events / Current orders.';
        }

        try {
            if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
                $shopId = $this->resolveShopeeShopId($shopRef);
                if ($shopId <= 0) {
                    return array_merge($base, ['error' => 'Pilih shop Shopee (shop_id dari shopee_syncs).']);
                }

                $pull = $this->shopeePull->pullAndIngestOrder($shopId, $orderRef);
                if ($pull['error'] !== null) {
                    return array_merge($base, [
                        'shop_ref' => (string) $shopId,
                        'error' => $pull['error'],
                    ]);
                }

                $result = array_merge($base, [
                    'ok' => true,
                    'order_status' => $pull['order_status'] ?? null,
                    'shop_ref' => (string) $shopId,
                    'webhook_event' => (bool) ($pull['webhook_event'] ?? false),
                    'shadow' => (bool) ($pull['shadow'] ?? false),
                    'jubelio_placeholder' => (bool) ($pull['jubelio_placeholder'] ?? false),
                ]);
            } else {
                $resolvedShopRef = $this->resolveTikTokShopRef($shopRef);
                if ($resolvedShopRef === null || $resolvedShopRef === '') {
                    return array_merge($base, ['error' => 'Pilih shop TikTok (shop_ref dari tiktok_shop_syncs).']);
                }

                $pull = $this->tikTokPull->pullAndIngestOrder($resolvedShopRef, $orderRef);
                if ($pull['error'] !== null) {
                    return array_merge($base, [
                        'shop_ref' => $resolvedShopRef,
                        'error' => $pull['error'],
                    ]);
                }

                $result = array_merge($base, [
                    'ok' => true,
                    'order_status' => $pull['order_status'] ?? null,
                    'shop_ref' => $resolvedShopRef,
                    'commerce_platform' => $pull['commerce_platform'] ?? null,
                    'webhook_event' => (bool) ($pull['webhook_event'] ?? false),
                    'inbox_event' => (bool) ($pull['inbox_event'] ?? false),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Marketplace order ref pull failed', [
                'channel' => $channel,
                'order_ref' => $orderRef,
                'shop_ref' => $shopRef,
                'message' => $e->getMessage(),
            ]);

            return array_merge($base, ['error' => $e->getMessage()]);
        }

        $result['return_signal'] = $this->detectReturnSignal($channel, $orderRef);

        if ($result['ok'] && ! $result['webhook_event'] && ! $result['shadow'] && ! ($result['inbox_event'] ?? false) && ! ($result['jubelio_placeholder'] ?? false)) {
            $result['warning'] = trim(($result['warning'] ?? '').' Tidak ada layer ingest aktif.');
        }

        return $result;
    }

    protected function resolveShopeeShopId(?string $shopRef): int
    {
        if (filled($shopRef) && is_numeric($shopRef)) {
            return (int) $shopRef;
        }

        return 0;
    }

    protected function resolveTikTokShopRef(?string $shopRef): ?string
    {
        if (filled($shopRef)) {
            return $shopRef;
        }

        if (! Schema::hasTable('tiktok_shop_syncs')) {
            return null;
        }

        if (TiktokShopSync::query()->count() === 1) {
            $only = TiktokShopSync::query()->value('shop_ref');

            return filled($only) ? (string) $only : null;
        }

        return null;
    }

    protected function detectReturnSignal(string $channel, string $orderRef): bool
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return false;
        }

        $order = MarketplaceChannelOrder::query()
            ->where('channel', $channel)
            ->where('order_ref', $orderRef)
            ->first();

        if ($order === null) {
            return false;
        }

        return $this->returnEligibility->passesReturnGate($order);
    }
}