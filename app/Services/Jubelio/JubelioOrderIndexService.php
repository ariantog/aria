<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JubelioOrderIndexService
{
    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [15, 50, 100];

    public function __construct(
        protected JubelioOrderWarehouseResolver $resolver,
    ) {}

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15;
    }

    public function resolveWarehouseId(Request $request): int
    {
        $mappedWarehouses = $this->resolver->mappedWarehousesForFilter();
        $allowedWarehouseIds = $mappedWarehouses->pluck('id')->all();
        $warehouseId = (int) $request->query('warehouse_id', 0);
        if ($warehouseId > 0 && ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            return 0;
        }

        return $warehouseId;
    }

    public function buildIndexQuery(Request $request): Builder
    {
        $warehouseId = $this->resolveWarehouseId($request);

        $q = Jubelioorder::query()->with('user')->orderBy('updated_at', 'desc');
        if ($request->status == 'warning') {
            $q->where('status', 2)->where('error_type', 2);
        } elseif ($request->status == 'success') {
            $q->where('status', 2)->where('error_type', 10);
        } elseif ($request->status == 'error') {
            $q->where('status', 1)->whereIn('error_type', JubelioOrderSyncStatus::ATTENTION_ERROR_TYPES);
        } elseif ($request->status == 'pending') {
            $q->where('status', 0);
        } elseif ($request->status === 'all') {
            // No sync-status filter.
        } elseif (! $request->invoice && $warehouseId <= 0) {
            $q->where('status', 0);
        }
        $q->when($request->invoice, fn ($q) => $q->where('invoice', 'like', '%'.$request->invoice.'%'));

        if ($warehouseId > 0) {
            $this->resolver->applyWarehouseFilter($q, $warehouseId);
        }

        return $q;
    }

    /**
     * @param  Collection<int, Jubelioorder>|LengthAwarePaginator  $orders
     */
    public function decorateOrdersForIndex(
        Collection|LengthAwarePaginator $orders,
        ?int $refreshedOrderId = null,
        ?array $refreshedSummary = null,
    ): void {
        $syncIndex = $this->resolver->syncIndex();
        $syncsByWarehouseId = $this->resolver->syncsGroupedByWarehouse();
        $warehouseIds = $orders->pluck('warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $warehousesById = $warehouseIds === []
            ? collect()
            : Addrbook::withTrashed()->whereIn('id', $warehouseIds)->get()->keyBy('id');

        $transform = function (Jubelioorder $order) use (
            $syncIndex,
            $warehousesById,
            $syncsByWarehouseId,
            $refreshedOrderId,
            $refreshedSummary,
        ) {
            $resolved = $this->resolver->resolveForIndex($order, $syncIndex, $warehousesById, $syncsByWarehouseId);
            $order->jubelio_warehouse = $resolved['jubelio_warehouse'];
            $order->aria_warehouse = $resolved['aria_warehouse'];
            $order->aria_warehouse_url = $resolved['aria_warehouse_url'];
            $order->payload_store_id = $resolved['payload_store_id'];
            $order->payload_location_id = $resolved['payload_location_id'];
            $order->list_summary = $resolved['summary'];

            if ($refreshedOrderId !== null
                && $refreshedOrderId > 0
                && $order->id === $refreshedOrderId
                && is_array($refreshedSummary)) {
                $order->list_summary = array_merge($order->list_summary, $refreshedSummary);
            }

            return $order;
        };

        if ($orders instanceof LengthAwarePaginator) {
            $orders->getCollection()->transform($transform);

            return;
        }

        $orders->transform($transform);
    }

    public function syncStatusLabel(Jubelioorder $order): string
    {
        return JubelioOrderSyncStatus::badgeLabel(
            (int) $order->status,
            $order->error_type !== null ? (int) $order->error_type : null,
            $order->type ?? 'SELL',
            $order->error,
        );
    }
}
