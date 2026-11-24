<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\MarketplaceOnOrderReservation;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\WarehouseOnOrderService;
use App\Support\ShopeeTestPushDetector;
use Illuminate\Support\Facades\Schema;

/**
 * Increment warehouse_item.on_order on Shopee awaiting-shipment pushes (no Jubelio placeholder required).
 */
class ShopeePushWebhookOnOrderApplicator
{
    public function __construct(
        private ShopeeJubelioQueuePrecreator $statusGate,
        private ShopeeSyncWarehouseResolver $shopeeSyncWarehouseResolver,
        private ShopeeOrderLineSummary $lineSummary,
        private WarehouseOnOrderService $warehouseOnOrder,
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('marketplace.shopee_on_order.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $fullPayload  Whole Shopee push envelope (code, shop_id, data, …)
     */
    public function applyFromPush(array $fullPayload): void
    {
        if (! $this->enabled() || ! $this->warehouseOnOrder->columnAvailable()) {
            return;
        }

        $pushCode = isset($fullPayload['code']) ? (int) $fullPayload['code'] : 0;
        if ($pushCode !== 3) {
            return;
        }

        $shopId = isset($fullPayload['shop_id']) ? (int) $fullPayload['shop_id'] : null;
        if ($this->shopeeInvoicePrefix->isFlexiExportShop($shopId)) {
            return;
        }

        $pushData = is_array($fullPayload['data'] ?? null) ? $fullPayload['data'] : [];
        $orderRef = $this->resolveOrderSn($pushData);
        $orderStatus = isset($pushData['status']) ? (string) $pushData['status'] : null;

        if ($orderRef === ''
            || ShopeeTestPushDetector::isTestPush($orderRef, $pushData, $fullPayload)
            || ! $this->statusGate->shouldTriggerForStatus($orderStatus)) {
            return;
        }

        $warehouseId = $this->shopeeSyncWarehouseResolver->ariaWarehouseIdForIngest($shopId, $pushData);
        if ($warehouseId <= 0) {
            return;
        }

        $lines = $this->lineSummary->fromOrderRow($pushData)['lines'] ?? [];
        if ($lines === []) {
            return;
        }

        $this->applyLines(
            MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
            $orderRef,
            $warehouseId,
            $lines,
        );
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

    /**
     * @param  array<string, mixed>  $pushData
     */
    private function resolveOrderSn(array $pushData): string
    {
        if (isset($pushData['ordersn']) && $pushData['ordersn'] !== '') {
            return trim((string) $pushData['ordersn']);
        }

        if (isset($pushData['order_sn']) && $pushData['order_sn'] !== '') {
            return trim((string) $pushData['order_sn']);
        }

        return '';
    }
}