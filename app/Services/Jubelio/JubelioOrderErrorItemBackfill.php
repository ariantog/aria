<?php

namespace App\Services\Jubelio;

use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\WarehouseItem;

class JubelioOrderErrorItemBackfill
{
    /**
     * First line-level error for a failed SELL order (SKU missing or insufficient stock).
     *
     * @param  array<string, mixed>  $payload
     * @return array{item_id: int, code: string, available: float, needed: float}|null
     */
    public function firstSellErrorItem(Jubelioorder $order, array $payload): ?array
    {
        if ($order->type !== 'SELL') {
            return null;
        }

        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        if ($storeId <= 0 || $locationId <= 0) {
            return null;
        }

        $sync = Jubeliosync::query()
            ->where('jubelio_store_id', $storeId)
            ->where('jubelio_location_id', $locationId)
            ->first();

        if (! $sync) {
            return null;
        }

        $qtyKey = 'qty';
        $lines = $payload['items'] ?? [];
        $codes = collect($lines)->pluck('item_code')->unique()->all();
        $existingProducts = Item::findManyBySkus($codes);

        foreach ($lines as $line) {
            $code = strtoupper((string) ($line['item_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (! isset($existingProducts[$code])) {
                return [
                    'item_id' => 0,
                    'code' => (string) ($line['item_code'] ?? ''),
                    'available' => 0.0,
                    'needed' => (float) ($line[$qtyKey] ?? 0),
                ];
            }
        }

        $warehouseId = (int) $sync->warehouse_id;

        foreach ($lines as $line) {
            $code = strtoupper((string) ($line['item_code'] ?? ''));
            $product = $existingProducts[$code] ?? null;
            if (! $product) {
                continue;
            }

            $needed = (float) ($line[$qtyKey] ?? 0);
            $available = (float) (WarehouseItem::query()
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $product->id)
                ->value('quantity') ?? 0);

            if ($needed > $available) {
                return [
                    'item_id' => (int) $product->id,
                    'code' => (string) $product->code,
                    'available' => $available,
                    'needed' => $needed,
                ];
            }
        }

        return null;
    }
}
