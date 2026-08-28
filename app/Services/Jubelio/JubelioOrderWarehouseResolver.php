<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    /**
     * @return Collection<int, Jubeliosync>
     */
    public function syncsForWarehouse(int $warehouseId): Collection
    {
        if ($warehouseId <= 0) {
            return collect();
        }

        return Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('jubelio_store_id', '>', 0)
            ->where('jubelio_location_id', '>', 0)
            ->get(['jubelio_store_id', 'jubelio_location_id', 'warehouse_id']);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistWarehouseKeysFromPayload(Jubelioorder $order, array $payload, ?Collection $syncIndex = null): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        if ($storeId <= 0 || $locationId <= 0) {
            return;
        }

        $warehouseId = $this->warehouseIdFromStoreLocation($storeId, $locationId, $syncIndex);

        if ((int) $order->jubelio_store_id === $storeId
            && (int) $order->jubelio_location_id === $locationId
            && (int) $order->warehouse_id === $warehouseId) {
            return;
        }

        Jubelioorder::query()->whereKey($order->id)->update([
            'jubelio_store_id' => $storeId,
            'jubelio_location_id' => $locationId,
            'warehouse_id' => $warehouseId,
        ]);

        $order->jubelio_store_id = $storeId;
        $order->jubelio_location_id = $locationId;
        $order->warehouse_id = $warehouseId;
    }

    public function applyWarehouseFilter(Builder $query, int $warehouseId): void
    {
        if ($warehouseId <= 0) {
            return;
        }

        $syncs = $this->syncsForWarehouse($warehouseId);

        $query->where(function (Builder $builder) use ($warehouseId, $syncs) {
            $builder->where('warehouse_id', $warehouseId);

            foreach ($syncs as $sync) {
                $builder->orWhere(function (Builder $inner) use ($sync) {
                    $inner->where('jubelio_store_id', $sync->jubelio_store_id)
                        ->where('jubelio_location_id', $sync->jubelio_location_id);
                });
            }
        });
    }

    public function scopeMissingWarehouseKeys(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->where('jubelio_store_id', 0)
                ->orWhere('jubelio_location_id', 0);
        });
    }

    public function hasMissingWarehouseKeysInScope(Builder $scope): bool
    {
        return (clone $scope)->where(function (Builder $query) {
            $query->where('jubelio_store_id', 0)
                ->orWhere('jubelio_location_id', 0);
        })->exists();
    }

    /**
     * One-off legacy repair: fetch Jubelio store/location for rows still missing keys.
     * Throttled so normal list/filter views do not hammer the API every request.
     */
    public function maybeBackfillForWarehouseFilter(Builder $scope, int $warehouseId, int $limit = 50): void
    {
        if ($warehouseId <= 0 || ! $this->hasMissingWarehouseKeysInScope($scope)) {
            return;
        }

        $cacheKey = "jubelio_wh_backfill:{$warehouseId}";

        if (Cache::has($cacheKey)) {
            return;
        }

        $updated = $this->backfillMissingWarehouseKeys($scope, $limit);

        if ($updated === 0) {
            Cache::put($cacheKey, true, now()->addHours(6));
        }
    }

    public function backfillMissingWarehouseKeys(Builder $scope, int $limit = 50): int
    {
        $payloadService = app(JubelioOrderPayloadService::class);
        $syncIndex = $this->syncIndex();
        $updated = 0;

        $this->scopeMissingWarehouseKeys(clone $scope)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Jubelioorder $order) use ($payloadService, $syncIndex, &$updated) {
                $payload = $payloadService->fetchOrEmpty($order);
                if ($payload === []) {
                    return;
                }

                $beforeStore = (int) $order->jubelio_store_id;
                $beforeLocation = (int) $order->jubelio_location_id;
                $beforeWarehouse = (int) $order->warehouse_id;
                $this->persistWarehouseKeysFromPayload($order, $payload, $syncIndex);

                if ((int) $order->jubelio_store_id !== $beforeStore
                    || (int) $order->jubelio_location_id !== $beforeLocation
                    || (int) $order->warehouse_id !== $beforeWarehouse) {
                    $updated++;
                }
            });

        return $updated;
    }

    public function resolveWarehouseId(Jubelioorder $order, ?Collection $syncIndex = null): int
    {
        if ((int) $order->warehouse_id > 0) {
            return (int) $order->warehouse_id;
        }

        if ((int) $order->jubelio_store_id > 0 && (int) $order->jubelio_location_id > 0) {
            return $this->warehouseIdFromStoreLocation(
                (int) $order->jubelio_store_id,
                (int) $order->jubelio_location_id,
                $syncIndex,
            );
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
     * @return array{
     *     jubelio_warehouse: ?string,
     *     aria_warehouse: ?string,
     *     aria_warehouse_id: ?int,
     *     aria_warehouse_url: ?string
     * }
     */
    public function resolve(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $index = $syncIndex ?? $this->syncIndex();
        $sync = null;

        if ((int) $order->jubelio_store_id > 0 && (int) $order->jubelio_location_id > 0) {
            $sync = $index->get($this->key((int) $order->jubelio_store_id, (int) $order->jubelio_location_id));
        }

        $payload = [];
        $storeId = (int) $order->jubelio_store_id;
        $locationId = (int) $order->jubelio_location_id;

        if ($sync === null) {
            $payload = $order->payloadArray();
            $storeId = (int) ($payload['store_id'] ?? 0);
            $locationId = (int) ($payload['location_id'] ?? 0);

            if ($storeId > 0 && $locationId > 0) {
                $sync = $index->get($this->key($storeId, $locationId));
            }
        }

        $warehouse = $sync?->warehouse;
        if ($warehouse === null) {
            $warehouseId = $this->resolveWarehouseId($order, $index);
            if ($warehouseId > 0) {
                $warehouse = Addrbook::query()->find($warehouseId);
            }
        }

        return [
            'jubelio_warehouse' => $sync?->jubelio_location_name
                ?? ($payload['location_name'] ?? null),
            'aria_warehouse' => $warehouse?->name,
            'aria_warehouse_id' => $warehouse?->id,
            'aria_warehouse_url' => $warehouse
                ? route('addrbook.type.show', ['type' => $warehouse->type_slug, 'addrbook' => $warehouse->id])
                : null,
        ];
    }

    private function key(int $storeId, int $locationId): string
    {
        return "{$storeId}:{$locationId}";
    }
}
