<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Illuminate\Support\Facades\Schema;

/**
 * After successful AWB print, align channel order summary with marketplace "ready to ship" wording.
 */
class MarketplaceChannelOrderAwbPromoter
{
    public const SHOPEE_AWB_STATUS = 'READY_TO_SHIP';

    public const TIKTOK_AWB_STATUS = 'AWAITING_COLLECTION';

    public function __construct(
        private MarketplaceWebhookStatusPresenter $statusPresenter,
        private ShopeeJubelioQueuePrecreator $shopeeJubelioQueuePrecreator,
        private TikTokJubelioQueuePrecreator $tikTokJubelioQueuePrecreator,
    ) {}

    /**
     * @param  list<string>  $orderRefs
     */
    public function promoteShopeeOrdersAfterAwb(array $orderRefs): int
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return 0;
        }

        $orderRefs = array_values(array_unique(array_filter(array_map(
            static fn ($ref) => trim((string) $ref),
            $orderRefs,
        ))));

        if ($orderRefs === []) {
            return 0;
        }

        $updated = 0;
        $now = now();

        foreach ($orderRefs as $orderRef) {
            $order = MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
                ->where('order_ref', $orderRef)
                ->first();

            if ($order === null) {
                continue;
            }

            if (! $this->statusPresenter->shouldPromoteShopeeStatusAfterAwb($order->order_status)) {
                continue;
            }

            if (! $this->statusPresenter->shouldReplaceChannelOrderStatus(
                MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
                $order->order_status,
                self::SHOPEE_AWB_STATUS,
            )) {
                continue;
            }

            $order->order_status = self::SHOPEE_AWB_STATUS;
            $order->updated_at = $now;
            $order->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  list<string>  $orderRefs
     */
    public function promoteTikTokOrdersAfterAwb(array $orderRefs): int
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return 0;
        }

        $orderRefs = array_values(array_unique(array_filter(array_map(
            static fn ($ref) => trim((string) $ref),
            $orderRefs,
        ))));

        if ($orderRefs === []) {
            return 0;
        }

        $updated = 0;
        $now = now();

        foreach ($orderRefs as $orderRef) {
            $order = MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where('order_ref', $orderRef)
                ->first();

            if ($order === null) {
                continue;
            }

            if (! $this->statusPresenter->shouldPromoteTikTokStatusAfterAwb($order->order_status)) {
                continue;
            }

            if (! $this->statusPresenter->shouldReplaceChannelOrderStatus(
                MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
                $order->order_status,
                self::TIKTOK_AWB_STATUS,
            )) {
                continue;
            }

            $order->order_status = self::TIKTOK_AWB_STATUS;
            $order->updated_at = $now;
            $order->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<int, list<string>>  $shopeeOrdersByShopId
     * @param  array<string, list<string>>  $tiktokOrdersByShopCipher
     */
    public function ensureJubelioQueueAfterAwb(
        array $shopeeOrdersByShopId,
        array $tiktokOrdersByShopCipher = [],
        ?int $printerUserId = null,
    ): int {
        return $this->shopeeJubelioQueuePrecreator->ensureQueueRowsFromAwbPrint($shopeeOrdersByShopId, $printerUserId)
            + $this->tikTokJubelioQueuePrecreator->ensureQueueRowsFromAwbPrint($tiktokOrdersByShopCipher, $printerUserId);
    }
}