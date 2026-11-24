<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only TikTok Get Order Detail → persist commerce_platform on unified marketplace tables.
 */
class MarketplaceTikTokCommercePlatformEnricher
{
    public function __construct(
        protected TikTokShopOpenApiService $tikTokOpenApi,
    ) {}

    public function shouldAttemptEnrichment(): bool
    {
        if (! filter_var(config('tiktok_shop.enrich_commerce_platform', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if ($this->tikTokOpenApi->isMock()) {
            return true;
        }

        if (! $this->tikTokOpenApi->liveAccessTokenAvailable()) {
            once(function (): void {
                Log::info('TikTok Shop commerce_platform enrich skipped — missing access_token');
            });

            return false;
        }

        return true;
    }

    /**
     * @return string|null Uppercase commerce platform (e.g. TOKOPEDIA) when resolved.
     */
    public function enrichOrder(string $orderRef): ?string
    {
        $orderRef = trim($orderRef);
        if ($orderRef === '') {
            return null;
        }

        if (! $this->shouldAttemptEnrichment()) {
            return null;
        }

        $channel = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;
        $channelOrder = null;
        $hasPlatformWhColumn = Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id');

        if (Schema::hasTable('marketplace_channel_orders')) {
            $channelOrder = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->first();

            $commerceFilled = $channelOrder !== null && filled($channelOrder->commerce_platform);
            $warehouseFilled = $channelOrder !== null
                && $hasPlatformWhColumn
                && filled($channelOrder->platform_warehouse_id);

            if ($commerceFilled && (! $hasPlatformWhColumn || $warehouseFilled)) {
                return strtoupper((string) $channelOrder->commerce_platform);
            }
        }

        try {
            $shopRef = filled($channelOrder?->shop_ref) ? (string) $channelOrder->shop_ref : null;
            $apiResponse = $this->tikTokOpenApi->getOrderDetail($orderRef, null, $shopRef);
            $platform = $this->tikTokOpenApi->extractCommercePlatform($apiResponse);
            $platformWarehouseId = $this->extractPlatformWarehouseIdFromApiResponse($apiResponse);

            if ($platformWarehouseId !== null && $hasPlatformWhColumn) {
                $this->persistPlatformWarehouseId($channel, $orderRef, $platformWarehouseId, $channelOrder);
            }

            if (! filled($platform)) {
                return $channelOrder !== null && filled($channelOrder->commerce_platform)
                    ? strtoupper((string) $channelOrder->commerce_platform)
                    : null;
            }

            $this->persistCommercePlatform($channel, $orderRef, $platform, $channelOrder);

            return $platform;
        } catch (Throwable $e) {
            Log::warning('TikTok Shop commerce_platform enrich failed', [
                'order_ref' => $orderRef,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function persistCommercePlatform(
        string $channel,
        string $orderRef,
        string $platform,
        ?MarketplaceChannelOrder $channelOrder,
    ): void {
        if (Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'commerce_platform')) {
            if ($channelOrder === null) {
                $channelOrder = MarketplaceChannelOrder::query()
                    ->where('channel', $channel)
                    ->where('order_ref', $orderRef)
                    ->first();
            }

            if ($channelOrder !== null && ! filled($channelOrder->commerce_platform)) {
                $channelOrder->commerce_platform = $platform;
                $channelOrder->save();
            }
        }

        if (Schema::hasTable('marketplace_webhook_events')
            && Schema::hasColumn('marketplace_webhook_events', 'commerce_platform')) {
            MarketplaceWebhookEvent::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->where(function ($query): void {
                    $query->whereNull('commerce_platform')
                        ->orWhere('commerce_platform', '');
                })
                ->update(['commerce_platform' => $platform]);
        }
    }

    public function persistPlatformWarehouseId(
        string $channel,
        string $orderRef,
        string $platformWarehouseId,
        ?MarketplaceChannelOrder $channelOrder,
    ): void {
        $platformWarehouseId = trim($platformWarehouseId);
        if ($platformWarehouseId === '' || $platformWarehouseId === '0') {
            return;
        }

        if (! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')) {
            return;
        }

        if ($channelOrder === null) {
            $channelOrder = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->first();
        }

        if ($channelOrder !== null && ! filled($channelOrder->platform_warehouse_id)) {
            $channelOrder->platform_warehouse_id = $platformWarehouseId;
            $channelOrder->save();
        }
    }

    protected function extractPlatformWarehouseIdFromApiResponse(?array $apiResponse): ?string
    {
        $order = $this->tikTokOpenApi->extractFirstOrder($apiResponse);
        if ($order === null) {
            return null;
        }

        foreach (['warehouse_id', 'shipping_warehouse_id', 'fulfillment_warehouse_id'] as $key) {
            $candidate = $order[$key] ?? null;
            if ($candidate === null || $candidate === '' || $candidate === 0 || $candidate === '0') {
                continue;
            }

            if (is_scalar($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }
}