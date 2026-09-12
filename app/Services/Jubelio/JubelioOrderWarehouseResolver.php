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
            ->with(['warehouse', 'customer'])
            ->get()
            ->keyBy(fn (Jubeliosync $sync) => $this->key((int) $sync->jubelio_store_id, (int) $sync->jubelio_location_id));
    }

    /**
     * @return Collection<int, Collection<int, Jubeliosync>>
     */
    public function syncsGroupedByWarehouse(): Collection
    {
        return Jubeliosync::query()
            ->with(['warehouse', 'customer'])
            ->where('warehouse_id', '>', 0)
            ->get()
            ->groupBy(fn (Jubeliosync $sync) => (int) $sync->warehouse_id);
    }

    /**
     * List page mapping — denormalized jubelioorders columns + jubeliosync only (no Jubelio API).
     *
     * @param  Collection<int, Addrbook>  $warehousesById
     * @param  Collection<int, Collection<int, Jubeliosync>>  $syncsByWarehouseId
     * @return array{
     *     jubelio_warehouse: ?string,
     *     aria_warehouse: ?string,
     *     aria_warehouse_url: ?string,
     *     payload_store_id: int,
     *     payload_location_id: int,
     *     summary: array{
     *         store_name: ?string,
     *         location_name: ?string,
     *         customer_name: ?string,
     *         transaction_date: null,
     *         real_total: null,
     *         item_count: int
     *     }
     * }
     */
    public function resolveForIndex(
        Jubelioorder $order,
        Collection $syncIndex,
        Collection $warehousesById,
        Collection $syncsByWarehouseId,
    ): array {
        $storeId = (int) $order->jubelio_store_id;
        $locationId = (int) $order->jubelio_location_id;
        $sync = ($storeId > 0 && $locationId > 0)
            ? $syncIndex->get($this->key($storeId, $locationId))
            : null;

        if ($order->type === 'RETURN') {
            $resolved = $this->resolveReturnSync($order, $syncIndex, [], false);
            if ($sync === null) {
                $sync = $resolved['sync'];
            }
            if ($storeId <= 0 || $locationId <= 0) {
                $storeId = $resolved['store_id'];
                $locationId = $resolved['location_id'];
            }
        }

        if ($sync === null && (int) $order->warehouse_id > 0) {
            $sync = $this->pickSyncForWarehouse((int) $order->warehouse_id, $syncsByWarehouseId);
            if ($sync !== null) {
                $storeId = (int) $sync->jubelio_store_id;
                $locationId = (int) $sync->jubelio_location_id;
            }
        }

        $warehouse = $sync?->warehouse ?? $warehousesById->get((int) $order->warehouse_id);

        return [
            'jubelio_warehouse' => $sync?->jubelio_location_name,
            'aria_warehouse' => $warehouse?->name,
            'aria_warehouse_url' => Addrbook::transactionsUrlFor($warehouse),
            'payload_store_id' => $storeId,
            'payload_location_id' => $locationId,
            'summary' => [
                'store_name' => $sync?->jubelio_store_name,
                'location_name' => $sync?->jubelio_location_name,
                'customer_name' => $sync?->customer?->name,
                'transaction_date' => null,
                'real_total' => null,
                'item_count' => 0,
            ],
        ];
    }

    /**
     * @param  Collection<int, Collection<int, Jubeliosync>>  $syncsByWarehouseId
     */
    public function pickSyncForWarehouse(int $warehouseId, Collection $syncsByWarehouseId): ?Jubeliosync
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $rows = $syncsByWarehouseId->get($warehouseId);
        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        return $rows->sortByDesc(fn (Jubeliosync $sync) => (int) $sync->customer_id)->first();
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
            $this->persistReturnWarehouseKeys($order, $payload, $syncIndex);

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
    private function persistReturnWarehouseKeys(Jubelioorder $order, array $payload, ?Collection $syncIndex = null): void
    {
        $resolved = $this->resolveReturnSync($order, $syncIndex, $payload);
        $columns = [
            'jubelio_store_id' => $resolved['store_id'],
            'jubelio_location_id' => $resolved['location_id'],
            'warehouse_id' => (int) ($resolved['sync']?->warehouse_id ?? $order->warehouse_id ?? 0),
        ];

        if ($columns['jubelio_store_id'] <= 0 || $columns['jubelio_location_id'] <= 0) {
            if ($columns['warehouse_id'] > 0 && (int) $order->warehouse_id !== $columns['warehouse_id']) {
                $this->updateColumnsWithoutTouchingTimestamps($order->id, [
                    'warehouse_id' => $columns['warehouse_id'],
                ]);
                $order->warehouse_id = $columns['warehouse_id'];
            }

            return;
        }

        if ((int) $order->jubelio_store_id === $columns['jubelio_store_id']
            && (int) $order->jubelio_location_id === $columns['jubelio_location_id']
            && (int) $order->warehouse_id === $columns['warehouse_id']) {
            return;
        }

        $this->updateColumnsWithoutTouchingTimestamps($order->id, $columns);

        $order->jubelio_store_id = $columns['jubelio_store_id'];
        $order->jubelio_location_id = $columns['jubelio_location_id'];
        $order->warehouse_id = $columns['warehouse_id'];
    }

    /**
     * RETURN payloads from Jubelio often omit store_id/location_id but include location_name.
     * Resolve mapping the same way on list, detail, and process paths.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     store_id: int,
     *     location_id: int,
     *     sync: ?Jubeliosync,
     *     location_name: ?string
     * }
     */
    public function resolveReturnSync(
        Jubelioorder $order,
        ?Collection $syncIndex = null,
        array $payload = [],
        bool $fetchPayloadWhenEmpty = true,
    ): array {
        if ($payload === [] && $fetchPayloadWhenEmpty) {
            $payload = $order->payloadArray();
        }

        $locationName = isset($payload['location_name']) && is_string($payload['location_name'])
            ? $payload['location_name']
            : null;

        [$storeId, $locationId] = $this->storeLocationIdsFromPayload($payload);

        if ($storeId <= 0 || $locationId <= 0) {
            $storeId = (int) $order->jubelio_store_id;
            $locationId = (int) $order->jubelio_location_id;
        }

        if ($storeId <= 0 || $locationId <= 0) {
            $sellOrderKeys = $this->storeLocationIdsFromSellJubelioOrder(
                (string) ($payload['salesorder_no'] ?? '')
            );
            if ($sellOrderKeys['store_id'] > 0 && $sellOrderKeys['location_id'] > 0) {
                $storeId = $sellOrderKeys['store_id'];
                $locationId = $sellOrderKeys['location_id'];
            }
        }

        $index = $syncIndex ?? $this->syncIndex();
        $sync = null;
        if ($storeId > 0 && $locationId > 0) {
            $sync = $index->get($this->key($storeId, $locationId));
        }

        $warehouseHint = (int) ($order->warehouse_id ?? 0);
        if ($sync === null && $locationName !== null) {
            $sync = $this->findSyncByLocationName($locationName, $warehouseHint > 0 ? $warehouseHint : null);
            if ($sync !== null) {
                $storeId = (int) $sync->jubelio_store_id;
                $locationId = (int) $sync->jubelio_location_id;
            }
        }

        if ($sync === null && $warehouseHint > 0) {
            $sync = $this->findSyncForWarehouse($warehouseHint, $locationName);
            if ($sync !== null) {
                $storeId = (int) $sync->jubelio_store_id;
                $locationId = (int) $sync->jubelio_location_id;
            }
        }

        return [
            'store_id' => $storeId,
            'location_id' => $locationId,
            'sync' => $sync,
            'location_name' => $locationName,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: int}
     */
    public function storeLocationIdsFromPayload(array $payload): array
    {
        return [
            (int) ($payload['store_id'] ?? 0),
            (int) ($payload['location_id'] ?? 0),
        ];
    }

    /**
     * @return array{store_id: int, location_id: int}
     */
    public function storeLocationIdsFromSellJubelioOrder(string $salesInvoice): array
    {
        if ($salesInvoice === '') {
            return ['store_id' => 0, 'location_id' => 0];
        }

        $sellOrder = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $salesInvoice)
            ->first(['jubelio_store_id', 'jubelio_location_id']);

        return [
            'store_id' => (int) ($sellOrder?->jubelio_store_id ?? 0),
            'location_id' => (int) ($sellOrder?->jubelio_location_id ?? 0),
        ];
    }

    public function findSyncByLocationName(string $locationName, ?int $warehouseId = null): ?Jubeliosync
    {
        $needle = $this->normalizeLocationName($locationName);
        if ($needle === '') {
            return null;
        }

        $candidates = Jubeliosync::query()
            ->with('warehouse')
            ->when($warehouseId !== null && $warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get()
            ->filter(function (Jubeliosync $sync) use ($needle): bool {
                $candidate = $this->normalizeLocationName((string) $sync->jubelio_location_name);

                return $candidate !== ''
                    && ($candidate === $needle
                        || str_contains($candidate, $needle)
                        || str_contains($needle, $candidate));
            })
            ->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($warehouseId !== null && $warehouseId > 0 && $candidates->isNotEmpty()) {
            return $candidates->first();
        }

        return null;
    }

    public function findSyncForWarehouse(int $warehouseId, ?string $locationName = null): ?Jubeliosync
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $query = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('jubelio_store_id', '>', 0)
            ->where('jubelio_location_id', '>', 0);

        if ($locationName !== null && $locationName !== '') {
            $sync = $this->findSyncByLocationName($locationName, $warehouseId);
            if ($sync !== null) {
                return $sync;
            }
        }

        return $query
            ->orderByDesc('customer_id')
            ->first();
    }

    /**
     * @return array{warehouse: ?Addrbook, customer: ?Addrbook}
     */
    public function resolveReturnParties(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $resolved = $this->resolveReturnSync($order, $syncIndex);
        $sync = $resolved['sync'];

        $warehouse = $sync?->warehouse ?? ($sync ? Addrbook::find($sync->warehouse_id) : null);
        $customer = $sync?->customer ?? ($sync ? Addrbook::find($sync->customer_id) : null);

        if (! $warehouse && (int) $order->warehouse_id > 0) {
            $warehouse = Addrbook::find($order->warehouse_id);
        }

        $payload = $order->payloadArray();
        $sell = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice', (string) ($payload['salesorder_no'] ?? ''))
            ->first();

        if ($sell) {
            $warehouse ??= Addrbook::find($sell->sender_id);
            $customer ??= Addrbook::find($sell->receiver_id);
        }

        return [
            'warehouse' => $warehouse,
            'customer' => $customer,
        ];
    }

    private function normalizeLocationName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return $normalized === null ? '' : mb_strtolower($normalized);
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

            foreach ($syncs as $sync) {
                $builder->orWhere(function (Builder $inner) use ($sync) {
                    $inner->where('type', 'RETURN')
                        ->where('jubelio_store_id', $sync->jubelio_store_id)
                        ->where('jubelio_location_id', $sync->jubelio_location_id);
                });
            }

            $builder->orWhere(function (Builder $inner) use ($warehouseId) {
                $inner->where('type', 'RETURN')
                    ->where('warehouse_id', $warehouseId)
                    ->where('jubelio_store_id', 0)
                    ->where('jubelio_location_id', 0);
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
            return $this->resolveReturn($order, $syncIndex);
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
            'aria_warehouse_url' => Addrbook::transactionsUrlFor($warehouse),
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
    private function resolveReturn(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $resolved = $this->resolveReturnSync($order, $syncIndex);
        $sync = $resolved['sync'];
        $warehouse = $sync?->warehouse ?? ($sync ? Addrbook::find($sync->warehouse_id) : null);

        if (! $warehouse && (int) $order->warehouse_id > 0) {
            $warehouse = Addrbook::find($order->warehouse_id);
        }

        if (! $warehouse) {
            $parties = $this->resolveReturnParties($order, $syncIndex);
            $warehouse = $parties['warehouse'];
        }

        return [
            'jubelio_warehouse' => $sync?->jubelio_location_name
                ?? $resolved['location_name'],
            'aria_warehouse' => $warehouse?->name,
            'aria_warehouse_id' => $warehouse?->id,
            'aria_warehouse_url' => Addrbook::transactionsUrlFor($warehouse),
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
