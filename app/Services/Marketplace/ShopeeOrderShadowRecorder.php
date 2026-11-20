<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceOrderShadow;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Carbon\Carbon;

class ShopeeOrderShadowRecorder
{
    public function __construct(
        private ShopeeStockOpenApiService $shopeeApi,
        private ShopeeJubelioShadowComparer $comparer,
        private ShopeeOrderLineSummary $shopeeLineSummary,
    ) {}

    public function recordFromPush(?int $shopId, string $orderSn, ?string $pushStatus, int $pushCode): MarketplaceOrderShadow
    {
        $detail = $this->shopeeApi->getOrderDetail($orderSn);
        $orderRow = is_array($detail) ? ($detail['response']['order_list'][0] ?? null) : null;
        $channelStatus = is_array($orderRow)
            ? (string) ($orderRow['order_status'] ?? $pushStatus ?? '')
            : (string) ($pushStatus ?? '');

        $lineSummary = $this->shopeeLineSummary->fromOrderRow(is_array($orderRow) ? $orderRow : null);

        $compare = $this->comparer->compare(
            $orderSn,
            $channelStatus !== '' ? $channelStatus : $pushStatus,
            $lineSummary,
        );

        return MarketplaceOrderShadow::query()->updateOrCreate(
            [
                'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
                'external_order_sn' => $orderSn,
            ],
            [
                'shop_id' => $shopId,
                'channel_status' => $channelStatus !== '' ? $channelStatus : $pushStatus,
                'last_push_code' => $pushCode,
                'compare_status' => $compare['compare_status'],
                'jubelio_invoice' => $compare['jubelio_invoice'],
                'jubelio_order_status' => $compare['jubelio_order_status'],
                'jubelio_order_id' => $compare['jubelio_order_id'],
                'channel_payload' => $detail,
                'line_summary' => $lineSummary,
                'jubelio_line_summary' => $compare['jubelio_line_summary'],
                'line_compare_status' => $compare['line_compare_status'],
                'compare_note' => $compare['compare_note'],
                'last_event_at' => Carbon::now(),
            ],
        );
    }
}