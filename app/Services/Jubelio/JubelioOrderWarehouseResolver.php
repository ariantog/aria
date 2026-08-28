<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Cache denormalized keys for SQL filtering only — payload remains authoritative for display.
     *
     * @param  array<string, mixed>  $payload
     */
    public function persistWarehouseKeysFromPayload(Jubelioorder $order, array $payload, ?Collection $syncIndex = null): void
    {
        if ($order->type === 'RETURN') {
            $this->persistReturnWarehouseKeys($order, $payload);

            return;
        }

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

        $this->updateColumnsWithoutTouchingTimestamps($order->id, [
            'jubelio_store_id' => $storeId,
            'jubelio_location_id' => $locationId,
            'warehouse_id' => $warehouseId,
        ]);

        $order->jubelio_store_id = $storeId;
        $order->jubelio_location_id = $locationId;
        $order->warehouse_id = $warehouseId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistReturnWarehouseKeys(Jubelioorder $order, array $payload): void
    {
        $salesInvoice = (string) ($payload['salesorder_no'] ?? '');
        if ($salesInvoice === '') {
            return;
        }

        $sell = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice', $salesInvoice)
            ->first();

        $warehouseId = (int) ($sell?->sender_id ?? 0);
        if ($warehouseId <= 0) {
            return;
        }

        if ((int) $order->warehouse_id === $warehouseId
            && (int) $order->jubelio_store_id === 0
            && (int) $order->jubelio_location_id === 0) {
            return;
        }

        $this->updateColumnsWithoutTouchingTimestamps($order->id, [
            'warehouse_id' => $warehouseId,
            'jubelio_store_id' => 0,
            'jubelio_location_id' => 0,
        ]);

        $order->warehouse_id = $warehouseId;
        $order->jubelio_store_id = 0;
        $order->jubelio_location_id = 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{jubelio_store_id: int, jubelio_location_id: int, warehouse_id: int}
     */
    public function sellWarehouseColumnsFromPayload(array $payload, ?Collection $syncIndex = null): array
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        return [
            'jubelio_store_id' => $storeId,
            'jubelio_location_id' => $locationId,
            'warehouse_id' => $this->warehouseIdFromStoreLocation($storeId, $locationId, $syncIndex),
        ];
    }

    public function applyWarehouseFilter(Builder $query, int $warehouseId): void
    {
        if ($warehouseId <= 0) {
            return;
        }

        $syncs = $this->syncsForWarehouse($warehouseId);

        $query->where(function (Builder $builder) use ($warehouseId, $syncs) {
            foreach ($syncs as $sync) {
                $builder->orWhere(function (Builder $inner) use ($sync) {
                    $inner->where('type', 'SELL')
                        ->where('jubelio_store_id', $sync->jubelio_store_id)
                        ->where('jubelio_location_id', $sync->jubelio_location_id);
                });
            }

            $builder->orWhere(function (Builder $inner) use ($warehouseId) {
                $inner->where('type', 'RETURN')
                    ->where('warehouse_id', $warehouseId);
            });
        });
    }

    /**
     * Display mapping — always payload → jubeliosync for SELL (same as pre-filter branch).
     *
     * @return array{
     *     jubelio_warehouse: ?string,
     *     aria_warehouse: ?string,
     *     aria_warehouse_id: ?int,
     *     aria_warehouse_url: ?string
     * }
     */
    public function resolve(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        if ($order->type === 'RETURN') {
            return $this->resolveReturn($order);
        }

        return $this->resolveSell($order, $syncIndex);
    }

    /**
     * @return array{
     *     jubelio_warehouse: ?string,
     *     aria_warehouse: ?string,
     *     aria_warehouse_id: ?int,
     *     aria_warehouse_url: ?string
     * }
     */
    private function resolveSell(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $payload = $order->payloadArray();
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        $sync = null;
        if ($storeId > 0 && $locationId > 0) {
            $index = $syncIndex ?? $this->syncIndex();
            $sync = $index->get($this->key($storeId, $locationId));
        }

        $warehouse = $sync?->warehouse;

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

    /**
     * @return array{
     *     jubelio_warehouse: ?string,
     *     aria_warehouse: ?string,
     *     aria_warehouse_id: ?int,
     *     aria_warehouse_url: ?string
     * }
     */
    private function resolveReturn(Jubelioorder $order): array
    {
        $payload = $order->payloadArray();
        $sell = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice', (string) ($payload['salesorder_no'] ?? ''))
            ->first();

        $warehouse = $sell ? Addrbook::query()->find($sell->sender_id) : null;

        return [
            'jubelio_warehouse' => $payload['location_name'] ?? null,
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

    /**
     * Re-fetch payload from Jubelio API, refresh filter columns, and return mapping diagnostics.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     store_id: int,
     *     location_id: int,
     *     location_name: ?string,
     *     aria_warehouse: ?string
     * }
     */
    public function refreshFromApi(Jubelioorder $order): array
    {
        Jubelioorder::clearPayloadCacheFor($order->id);
        app(JubelioOrderPayloadService::class)->forget($order->id);

        $payloadService = app(JubelioOrderPayloadService::class);
        $payload = $payloadService->fetchOrEmpty($order);
        if ($payload === []) {
            return [
                'success' => false,
                'message' => 'Payload kosong — cek koneksi Jubelio atau order ID.',
                'store_id' => 0,
                'location_id' => 0,
                'location_name' => null,
                'aria_warehouse' => null,
            ];
        }

        $syncIndex = $this->syncIndex();
        $this->persistWarehouseKeysFromPayload($order, $payload, $syncIndex);

        if ($order->status === 1 && $order->error_type === 1 && $order->type === 'SELL') {
            $firstError = app(JubelioOrderErrorItemBackfill::class)->firstSellErrorItem($order, $payload);
            if ($firstError !== null) {
                $this->updateColumnsWithoutTouchingTimestamps($order->id, [
                    'stock_error_items' => json_encode([$firstError]),
                ]);
            }
        }

        $order->refresh();
        $resolved = $this->resolve($order, $syncIndex);
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $locationName = $payload['location_name'] ?? null;
        $ariaWarehouse = $resolved['aria_warehouse'] ?? null;

        if ($ariaWarehouse) {
            $message = "Mapping: store {$storeId} / loc {$locationId} ({$locationName}) → {$ariaWarehouse}";
        } elseif ($storeId > 0 && $locationId > 0) {
            $message = "store {$storeId} / loc {$locationId} ({$locationName}) belum ada di Jubelio Sync — tambahkan mapping di Jubelio Sync.";
        } else {
            $message = "Payload tidak punya store_id/location_id (location_name: {$locationName}) — tidak bisa map ke gudang Aria.";
        }

        return [
            'success' => true,
            'message' => $message,
            'store_id' => $storeId,
            'location_id' => $locationId,
            'location_name' => is_string($locationName) ? $locationName : null,
            'aria_warehouse' => $ariaWarehouse,
        ];
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function updateColumnsWithoutTouchingTimestamps(int $orderId, array $columns): void
    {
        DB::table('jubelioorders')->where('id', $orderId)->update($columns);
    }
}
