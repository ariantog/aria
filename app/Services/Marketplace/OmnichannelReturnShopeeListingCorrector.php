<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3: Shopee return webhook → push listing down to Aria sellable (no warehouse_item change).
 */
class OmnichannelReturnShopeeListingCorrector
{
    public function __construct(
        private OmnichannelPostReturnEligibility $returnEligibility,
        private ShopeeOrderLineSummary $lineSummary,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
        private MarketplaceListingStockPusher $pusher,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('marketplace.listing_push.return_shopee_listing_correct', false);
    }

    /**
     * @param  array<string, mixed>  $fullPayload
     * @return array{pushed: int, skipped: int, errors: list<string>}
     */
    public function applyFromPush(array $fullPayload): array
    {
        $stats = ['pushed' => 0, 'skipped' => 0, 'errors' => []];

        if (! $this->enabled()) {
            return $stats;
        }

        $pushCode = isset($fullPayload['code']) ? (int) $fullPayload['code'] : 0;
        if ($pushCode !== 3) {
            return $stats;
        }

        $shopId = isset($fullPayload['shop_id']) ? (int) $fullPayload['shop_id'] : null;
        if ($this->invoicePrefix->isFlexiExportShop($shopId)) {
            return $stats;
        }

        $pushData = is_array($fullPayload['data'] ?? null) ? $fullPayload['data'] : [];
        $orderStatus = isset($pushData['status']) ? strtoupper(trim((string) $pushData['status'])) : '';
        if (! $this->isReturnStatus($orderStatus)) {
            return $stats;
        }

        $orderRef = $this->resolveOrderSn($pushData);
        if ($orderRef === '') {
            return $stats;
        }

        $lines = $this->lineSummary->fromOrderRow($pushData)['lines'] ?? [];
        if ($lines === []) {
            return $stats;
        }

        $warehouseBefore = $this->snapshotWarehouseQty($lines);

        foreach ($lines as $line) {
            $sku = strtoupper(trim((string) ($line['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $item = Item::query()->whereRaw('UPPER(code) = ?', [$sku])->first();
            if ($item === null) {
                $stats['skipped']++;
                $stats['errors'][] = 'SKU '.$sku.' tidak ditemukan.';

                continue;
            }

            $result = $this->pusher->pushShopeeShopSellableForItem(
                $item,
                $shopId,
                MarketplaceListingStockPusher::TRIGGER_RETURN_WEBHOPEE_CORRECT,
                $orderRef,
            );
            $stats['pushed'] += $result['pushed'];
            $stats['skipped'] += $result['skipped'];
            $stats['errors'] = array_merge($stats['errors'], $result['errors']);
        }

        $warehouseAfter = $this->snapshotWarehouseQty($lines);
        if ($warehouseBefore !== $warehouseAfter) {
            $stats['errors'][] = 'warehouse_item changed during listing correct — unexpected.';
        }

        return $stats;
    }

    /**
     * @param  list<array{sku: string, qty: float}>  $lines
     * @return array<string, int>
     */
    private function snapshotWarehouseQty(array $lines): array
    {
        if (! Schema::hasTable('warehouse_item')) {
            return [];
        }

        $snap = [];
        foreach ($lines as $line) {
            $sku = strtoupper(trim((string) ($line['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $item = Item::query()->whereRaw('UPPER(code) = ?', [$sku])->first();
            if ($item === null) {
                continue;
            }
            WarehouseItem::query()
                ->where('item_id', $item->id)
                ->get(['warehouse_id', 'quantity', 'on_order'])
                ->each(function (WarehouseItem $row) use (&$snap, $item): void {
                    $snap[$item->id.':'.$row->warehouse_id] = (int) $row->quantity * 1000 + (int) $row->on_order;
                });
        }

        return $snap;
    }

    private function isReturnStatus(string $status): bool
    {
        $statuses = array_map('strtoupper', $this->returnEligibility->candidateStatuses());

        return in_array($status, $statuses, true);
    }

    /**
     * @param  array<string, mixed>  $pushData
     */
    private function resolveOrderSn(array $pushData): string
    {
        if (isset($pushData['ordersn']) && filled($pushData['ordersn'])) {
            return (string) $pushData['ordersn'];
        }

        if (isset($pushData['order_sn']) && filled($pushData['order_sn'])) {
            return (string) $pushData['order_sn'];
        }

        return '';
    }
}