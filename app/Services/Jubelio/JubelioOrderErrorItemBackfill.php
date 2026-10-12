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
        if (! Jubeliosync::hasMappedStoreLocationPair($storeId, $locationId)) {
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
        $jubelioIds = collect($lines)
            ->map(fn (array $line) => (int) ($line['item_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->all();
        $existingProducts = Item::findManyByJubelioItemIds($jubelioIds);

        foreach ($lines as $line) {
            $jubelioItemId = (int) ($line['item_id'] ?? 0);
            if ($jubelioItemId <= 0) {
                return [
                    'item_id' => 0,
                    'code' => (string) ($line['item_code'] ?? ''),
                    'available' => 0.0,
                    'needed' => JubelioOrderLineQuantity::resolve($line, $qtyKey),
                ];
            }

            if (! $existingProducts->has($jubelioItemId)) {
                return [
                    'item_id' => 0,
                    'code' => (string) ($line['item_code'] ?? (string) $jubelioItemId),
                    'available' => 0.0,
                    'needed' => JubelioOrderLineQuantity::resolve($line, $qtyKey),
                ];
            }
        }

        $warehouseId = (int) $sync->warehouse_id;

        foreach ($lines as $line) {
            $jubelioItemId = (int) ($line['item_id'] ?? 0);
            $product = $jubelioItemId > 0 ? $existingProducts->get($jubelioItemId) : null;
            if (! $product) {
                continue;
            }

            $needed = JubelioOrderLineQuantity::resolve($line, $qtyKey);
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
