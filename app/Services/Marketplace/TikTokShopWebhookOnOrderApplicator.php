<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\MarketplaceOnOrderReservation;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use App\Services\WarehouseOnOrderService;
use Illuminate\Support\Facades\Schema;

/**
 * Increment warehouse_item.on_order on TikTok paid / awaiting-shipment webhooks only.
 */
class TikTokShopWebhookOnOrderApplicator
{
    public function __construct(
        private MarketplaceWebhookStatusPresenter $statusPresenter,
        private MarketplaceWebhookShopWarehouseResolver $shopWarehouseResolver,
        private TikTokOrderLineSummary $lineSummary,
        private WarehouseOnOrderService $warehouseOnOrder,
        private TikTokShopOpenApiService $openApi,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('marketplace.tiktok_on_order.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyFromWebhook(array $payload): void
    {
        if (! $this->enabled() || ! $this->warehouseOnOrder->columnAvailable()) {
            return;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $orderRef = isset($data['order_id']) ? trim((string) $data['order_id']) : '';
        $orderStatus = isset($data['order_status']) ? (string) $data['order_status'] : null;

        if ($orderRef === '' || ! $this->shouldTriggerForStatus($orderStatus)) {
            return;
        }

        $shopRef = isset($payload['shop_id']) ? (string) $payload['shop_id'] : '';
        $warehouseId = $this->shopWarehouseResolver->resolveAriaWarehouseId($shopRef, $payload);
        if ($warehouseId <= 0) {
            return;
        }

        $lines = $this->lineSummary->fromWebhookPayload($payload);
        if ($lines === []) {
            $detail = $this->openApi->getOrderDetail($orderRef);
            $lines = $this->lineSummary->fromOrderDetailResponse($detail);
        }

        if ($lines === []) {
            return;
        }

        $this->applyLines(
            MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
            $orderRef,
            $warehouseId,
            $lines,
        );
    }

    public function shouldTriggerForStatus(?string $orderStatus): bool
    {
        if ($orderStatus === null || trim($orderStatus) === '') {
            return false;
        }

        $bucket = $this->statusPresenter->normalizeBucket(
            MarketplaceWebhookStatusPresenter::CHANNEL_TIKTOK,
            null,
            $orderStatus,
        );

        return $bucket === 'awaiting_shipment';
    }

    /**
     * @param  list<array{sku: string, qty: float}>  $lines
     */
    public function applyLines(string $channel, string $orderRef, int $warehouseId, array $lines): void
    {
        if ($warehouseId <= 0 || $lines === [] || ! Schema::hasTable('marketplace_on_order_reservations')) {
            return;
        }

        foreach ($lines as $line) {
            $sku = strtoupper(trim((string) ($line['sku'] ?? '')));
            $qty = (float) ($line['qty'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }

            $item = Item::query()
                ->where('code', $sku)
                ->orWhere('legacy_code', $sku)
                ->orderBy('id')
                ->first();

            if ($item === null) {
                continue;
            }

            $itemId = (int) $item->id;

            $existing = MarketplaceOnOrderReservation::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->first();

            if ($existing !== null) {
                continue;
            }

            MarketplaceOnOrderReservation::query()->create([
                'channel' => $channel,
                'order_ref' => $orderRef,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'qty' => $qty,
            ]);

            $this->warehouseOnOrder->reserveSkuLines($warehouseId, [['sku' => $sku, 'qty' => $qty]]);
        }
    }
}