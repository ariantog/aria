<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class JubelioOrderWarehouseResolver
{
    /**
     * @return Collection<string, Jubeliosync>
     */
    public function syncIndex(): Collection
    {
        return Jubeliosync::query()
            ->with('warehouse')
            ->get()
            ->keyBy(fn (Jubeliosync $sync) => $this->key((int) $sync->jubelio_store_id, (int) $sync->jubelio_location_id));
    }

    /**
     * Mapped Aria warehouses for list filters (one entry per warehouse_id).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function mappedWarehousesForFilter(): Collection
    {
        return Jubeliosync::query()
            ->where('warehouse_id', '>', 0)
            ->with('warehouse')
            ->get()
            ->filter(fn (Jubeliosync $sync) => $sync->warehouse !== null)
            ->unique('warehouse_id')
            ->map(fn (Jubeliosync $sync) => [
                'id' => (int) $sync->warehouse_id,
                'name' => (string) $sync->warehouse->name,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function warehouseIdFromStoreLocation(int $storeId, int $locationId, ?Collection $syncIndex = null): int
    {
        if ($storeId <= 0 || $locationId <= 0) {
            return 0;
        }

        $index = $syncIndex ?? $this->syncIndex();
        $sync = $index->get($this->key($storeId, $locationId));

        return (int) ($sync?->warehouse_id ?? 0);
    }

    public function resolveWarehouseId(Jubelioorder $order, ?Collection $syncIndex = null): int
    {
        if ((int) $order->warehouse_id > 0) {
            return (int) $order->warehouse_id;
        }

        if ($order->type === 'RETURN') {
            $payload = $order->payloadArray();
            $salesInvoice = (string) ($payload['salesorder_no'] ?? '');
            if ($salesInvoice === '') {
                return 0;
            }

            $sell = Transaction::query()
                ->where('type', Transaction::TYPE_SELL)
                ->where('invoice', $salesInvoice)
                ->first();

            return (int) ($sell?->sender_id ?? 0);
        }

        $payload = $order->payloadArray();

        return $this->warehouseIdFromStoreLocation(
            (int) ($payload['store_id'] ?? 0),
            (int) ($payload['location_id'] ?? 0),
            $syncIndex,
        );
    }

    /**
     * @return array{jubelio_warehouse: ?string, aria_warehouse: ?string}
     */
    public function resolve(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $payload = $order->payloadArray();
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        $sync = null;
        if ($storeId > 0 && $locationId > 0) {
            $index = $syncIndex ?? $this->syncIndex();
            $sync = $index->get($this->key($storeId, $locationId));
        }

        $ariaWarehouse = $sync?->warehouse?->name;
        if ($ariaWarehouse === null && (int) $order->warehouse_id > 0) {
            $ariaWarehouse = Addrbook::query()->whereKey($order->warehouse_id)->value('name');
        }

        return [
            'jubelio_warehouse' => $sync?->jubelio_location_name
                ?? ($payload['location_name'] ?? null),
            'aria_warehouse' => $ariaWarehouse,
        ];
    }

    private function key(int $storeId, int $locationId): string
    {
        return "{$storeId}:{$locationId}";
    }
}
