<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceOrderShadow;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeTestPushDetector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShopeeOrderShadowRecorder
{
    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeJubelioShadowComparer $comparer,
        private ShopeeOrderLineSummary $shopeeLineSummary,
    ) {}

    /**
     * Minimal row so /omnichannel list updates without API calls (full detail on detail page or refresh).
     */
    /**
     * @param  array<string, mixed>  $pushData  Shopee push `data` object (optional).
     * @param  array<string, mixed>  $fullPayload  Full push JSON (optional).
     */
    public function upsertStubFromPush(
        ?int $shopId,
        string $orderSn,
        ?string $pushStatus,
        int $pushCode,
        array $pushData = [],
        array $fullPayload = [],
    ): MarketplaceOrderShadow {
        $shadow = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->where('external_order_sn', $orderSn)
            ->first();

        $testPush = ShopeeTestPushDetector::isTestPush($orderSn, $pushData, $fullPayload);

        $attributes = [
            'shop_id' => $shopId,
            'channel_status' => $pushStatus,
            'last_push_code' => $pushCode,
            'last_event_at' => Carbon::now(),
        ];

        if ($testPush) {
            $attributes['compare_note'] = ShopeeTestPushDetector::COMPARE_NOTE;
        }

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
        $detail = null;

        try {
            $detail = $this->shopeeApi->getOrderDetail($orderSn);
        } catch (Throwable $e) {
            Log::warning('Shopee get_order_detail failed — shadow stub retained', [
                'ordersn' => $orderSn,
                'message' => $e->getMessage(),
            ]);
        }

        $orderRow = is_array($detail) ? ($detail['response']['order_list'][0] ?? null) : null;
        if (! is_array($orderRow)) {
            return $this->retainStubAfterDetailUnavailable($shopId, $orderSn, $pushStatus, $pushCode);
        }
        $channelStatus = (string) ($orderRow['order_status'] ?? $pushStatus ?? '');

        $lineSummary = $this->shopeeLineSummary->fromOrderRow($orderRow);

        $compare = $this->comparer->compare(
            $orderSn,
            $channelStatus !== '' ? $channelStatus : $pushStatus,
            $lineSummary,
        );

        $compareNote = $compare['compare_note'];
        if (ShopeeTestPushDetector::isTestPush($orderSn)) {
            $compareNote = filled($compareNote)
                ? ShopeeTestPushDetector::COMPARE_NOTE.'; '.$compareNote
                : ShopeeTestPushDetector::COMPARE_NOTE;
        }

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
                'compare_note' => $compareNote,
                'last_event_at' => Carbon::now(),
            ],
        );
    }

    private function retainStubAfterDetailUnavailable(
        ?int $shopId,
        string $orderSn,
        ?string $pushStatus,
        int $pushCode,
    ): MarketplaceOrderShadow {
        $shadow = $this->upsertStubFromPush($shopId, $orderSn, $pushStatus, $pushCode);

        if ($shadow->compare_note === ShopeeTestPushDetector::COMPARE_NOTE) {
            return $shadow->refresh();
        }

        $note = trim((string) ($shadow->compare_note ?? ''));
        $shadow->compare_note = $note === ''
            ? 'shopee_api_fetch_failed'
            : $note.'; shopee_api_fetch_failed';
        $shadow->save();

        return $shadow->refresh();
    }
}