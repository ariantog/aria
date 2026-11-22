<?php

namespace App\Services;

use App\Models\Item;
use App\Models\WarehouseItem;
use App\Services\Marketplace\ShopeeOrderLineSummary;
use Illuminate\Support\Facades\Schema;

/**
 * Per-warehouse marketplace reservations — increments on_order only, never physical quantity.
 */
class WarehouseOnOrderService
{
    public function __construct(
        private ShopeeOrderLineSummary $shopeeLineSummary,
    ) {}

    public function columnAvailable(): bool
    {
        return Schema::hasTable('warehouse_item')
            && Schema::hasColumn('warehouse_item', 'on_order');
    }

    /**
     * Reserve qty when a new Shopee push placeholder row is created (push payload may include item_list).
     *
     * @param  array<string, mixed>  $pushData
     */
    public function reserveFromShopeePushData(int $warehouseId, array $pushData): void
    {
        if ($warehouseId <= 0 || ! $this->columnAvailable()) {
            return;
        }

        $itemList = $pushData['item_list'] ?? null;
        if (! is_array($itemList) || $itemList === []) {
            return;
        }

        $summary = $this->shopeeLineSummary->fromOrderRow(['item_list' => $itemList]);
        $this->reserveSkuLines($warehouseId, $summary['lines'] ?? []);
    }

    /**
     * @param  list<array{sku: string, qty: float}>  $lines
     */
    public function reserveSkuLines(int $warehouseId, array $lines): void
    {
        if ($warehouseId <= 0 || $lines === [] || ! $this->columnAvailable()) {
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

            WarehouseItem::applyOnOrderDelta($warehouseId, (int) $item->id, $qty);
        }
    }

    /**
     * Release on_order when physical stock is deducted (e.g. Jubelio SELL post).
     */
    public function releaseOnPhysicalDeduct(int $warehouseId, int $itemId, float $deductQty): void
    {
        if ($warehouseId <= 0 || $itemId <= 0 || $deductQty <= 0 || ! $this->columnAvailable()) {
            return;
        }

        $releaseQty = min($deductQty, $this->currentOnOrder($warehouseId, $itemId));
        if ($releaseQty > 0) {
            WarehouseItem::applyOnOrderDelta($warehouseId, $itemId, -$releaseQty);
        }
    }

    private function currentOnOrder(int $warehouseId, int $itemId): float
    {
        $row = WarehouseItem::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->first();

        return $row === null ? 0.0 : max(0.0, (float) ($row->on_order ?? 0));
    }
}