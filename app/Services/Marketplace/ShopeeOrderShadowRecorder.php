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

    /**
     * Minimal row so /omnichannel list updates without a queue worker (full detail via job or detail refresh).
     */
    public function upsertStubFromPush(?int $shopId, string $orderSn, ?string $pushStatus, int $pushCode): MarketplaceOrderShadow
    {
        $shadow = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->where('external_order_sn', $orderSn)
            ->first();

        $attributes = [
            'shop_id' => $shopId,
            'channel_status' => $pushStatus,
            'last_push_code' => $pushCode,
            'last_event_at' => Carbon::now(),
        ];

        if ($shadow === null) {
            return MarketplaceOrderShadow::query()->create([
                ...$attributes,
                'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
                'external_order_sn' => $orderSn,
                'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
                'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_SKIPPED,
            ]);
        }

        $shadow->fill($attributes);
        $shadow->save();

        return $shadow->refresh();
    }

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