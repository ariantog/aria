<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MarketplaceWebhookInboxQuery
{
    public function __construct(
        protected MarketplaceWebhookShopWarehouseResolver $shopResolver,
        protected MarketplaceChannelOrderOpsBucket $opsBucket,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parseFilters(Request $request): array
    {
        $channel = strtolower(trim((string) $request->query('channel', 'all')));
        if (! in_array($channel, ['all', 'shopee', 'tiktok', 'tokopedia'], true)) {
            $channel = 'all';
        }

        $view = strtolower(trim((string) $request->query('view', 'events')));
        if (! in_array($view, ['events', 'orders'], true)) {
            $view = 'events';
        }

        $dateTo = $this->parseDate($request->query('date_to'), Carbon::now()->endOfDay());
        $dateFrom = $this->parseDate(
            $request->query('date_from'),
            Carbon::now()->subDays(7)->startOfDay(),
        );

        if (is_string($request->query('date_to')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($request->query('date_to')))) {
            $dateTo = $dateTo->copy()->endOfDay();
        }

        if (is_string($request->query('date_from')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($request->query('date_from')))) {
            $dateFrom = $dateFrom->copy()->startOfDay();
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $shopRef = trim((string) $request->query('shop_ref', ''));
        $shopRef = $shopRef !== '' ? $shopRef : null;

        $warehouseId = (int) $request->query('warehouse_id', 0);

        $orderStatus = trim((string) $request->query('order_status', ''));
        $orderStatus = $orderStatus !== '' ? $orderStatus : null;

        $orderRef = trim((string) $request->query('order_ref', ''));
        $orderRef = $orderRef !== '' ? $orderRef : null;

        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);

        $opsBucket = null;
        if ($view === 'orders') {
            $hasOpsParam = $request->query->has('ops_bucket');
            $rawOps = $hasOpsParam ? (string) $request->query('ops_bucket', '') : '';
            if ($hasOpsParam && strtolower(trim($rawOps)) === MarketplaceChannelOrderOpsBucket::BUCKET_ALL) {
                $opsBucket = MarketplaceChannelOrderOpsBucket::BUCKET_ALL;
            } elseif ($hasOpsParam && $rawOps !== '') {
                $opsBucket = MarketplaceChannelOrderOpsBucket::normalize($rawOps, $view);
            } else {
                $opsBucket = MarketplaceChannelOrderOpsBucket::DEFAULT_ORDERS_VIEW;
            }
        }

        return [
            'channel' => $channel,
            'view' => $view,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'shop_ref' => $shopRef,
            'warehouse_id' => $warehouseId,
            'order_status' => $orderStatus,
            'order_ref' => $orderRef,
            'per_page' => $perPage,
            'ops_bucket' => $opsBucket,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function eventsQuery(array $filters): Builder
    {
        $query = MarketplaceWebhookEvent::query()->orderByDesc('received_at')->orderByDesc('id');

        $this->applyChannel($query, $filters['channel']);
        $this->applyDateRange($query, 'received_at', $filters);
        $this->applyShopAndWarehouse($query, 'shop_ref', $filters);
        $this->applyOrderStatus($query, 'order_status', $filters['order_status']);
        $this->applyOrderRef($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function ordersQuery(array $filters): Builder
    {
        $opsBucket = $filters['ops_bucket'] ?? null;
        $query = MarketplaceChannelOrder::query()
            ->with([
                'lastWebhookEvent:id,channel,payload,commerce_platform',
            ]);

        $this->applyChannel($query, $filters['channel']);
        $this->applyDateRange($query, 'last_event_at', $filters);
        $this->applyShopAndWarehouse($query, 'shop_ref', $filters);
        $this->applyOrderStatus($query, 'order_status', $filters['order_status']);
        $this->applyOrderRef($query, $filters);

        $skipOpsBucket = filled($filters['order_ref'] ?? null);
        if (is_string($opsBucket) && $opsBucket !== '' && ! $skipOpsBucket) {
            $this->opsBucket->applyOpsBucketFilter($query, $opsBucket, $filters['channel']);
        }

        if ($opsBucket === MarketplaceChannelOrderOpsBucket::BUCKET_ALL && ! $skipOpsBucket) {
            $this->opsBucket->applyAllBucketSort($query);
        } else {
            $query->orderByDesc('last_event_at')->orderByDesc('id');
        }

        return $query;
    }

    /**
     * Base orders query with shared list filters but without ops_bucket (for stat cards).
     *
     * @param  array<string, mixed>  $filters
     */
    public function ordersQueryForStats(array $filters): Builder
    {
        $filtersWithoutOps = $filters;
        $filtersWithoutOps['ops_bucket'] = null;

        $query = MarketplaceChannelOrder::query();
        $this->applyChannel($query, $filtersWithoutOps['channel']);
        $this->applyDateRange($query, 'last_event_at', $filtersWithoutOps);
        $this->applyShopAndWarehouse($query, 'shop_ref', $filtersWithoutOps);
        $this->applyOrderStatus($query, 'order_status', $filtersWithoutOps['order_status']);
        $this->applyOrderRef($query, $filtersWithoutOps);

        return $query;
    }

    /**
     * Distinct order_status values for the filter dropdown (same scope minus status filter).
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    public function distinctOrderStatuses(array $filters, string $view): array
    {
        $filtersWithoutStatus = $filters;
        $filtersWithoutStatus['order_status'] = null;
        if ($view === 'orders') {
            $filtersWithoutStatus['ops_bucket'] = null;
        }

        $query = $view === 'orders'
            ? $this->ordersQuery($filtersWithoutStatus)
            : $this->eventsQuery($filtersWithoutStatus);

        $column = 'order_status';

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(100)
            ->pluck($column)
            ->all();
    }

    protected function parseDate(mixed $value, Carbon $default): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $default->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $default->copy();
        }
    }

    /**
     * @param  Builder<MarketplaceWebhookEvent>|Builder<MarketplaceChannelOrder>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyShopAndWarehouse(Builder $query, string $shopColumn, array $filters): void
    {
        if (filled($filters['shop_ref'])) {
            $query->where($shopColumn, $filters['shop_ref']);
        }

        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            return;
        }

        $shopRefs = $this->shopResolver->shopRefsForWarehouse($warehouseId);
        if ($shopRefs === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($shopColumn, $shopRefs);
    }

    /**
     * @param  Builder<MarketplaceWebhookEvent>|Builder<MarketplaceChannelOrder>  $query
     */
    protected function applyChannel(Builder $query, string $channel): void
    {
        if ($channel === 'all') {
            return;
        }

        if ($channel === 'tokopedia') {
            $query->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->whereRaw('UPPER(COALESCE(commerce_platform, "")) = ?', ['TOKOPEDIA']);

            return;
        }

        if ($channel === 'tiktok') {
            $query->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where(function (Builder $inner): void {
                    $inner->whereNull('commerce_platform')
                        ->orWhere('commerce_platform', '')
                        ->orWhereRaw('UPPER(commerce_platform) != ?', ['TOKOPEDIA']);
                });

            return;
        }

        $query->where('channel', $channel);
    }

    /**
     * @param  Builder<MarketplaceWebhookEvent>|Builder<MarketplaceChannelOrder>  $query
     */
    protected function applyOrderStatus(Builder $query, string $column, ?string $orderStatus): void
    {
        if (! filled($orderStatus)) {
            return;
        }

        $query->where($column, 'like', '%'.$orderStatus.'%');
    }

    /**
     * When searching by order ref, skip the date window so ops can locate an order by id alone.
     *
     * @param  Builder<MarketplaceWebhookEvent>|Builder<MarketplaceChannelOrder>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyDateRange(Builder $query, string $column, array $filters): void
    {
        if (filled($filters['order_ref'] ?? null)) {
            return;
        }

        $query->whereBetween($column, [$filters['date_from'], $filters['date_to']]);
    }

    /**
     * @param  Builder<MarketplaceWebhookEvent>|Builder<MarketplaceChannelOrder>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyOrderRef(Builder $query, array $filters): void
    {
        $orderRef = $filters['order_ref'] ?? null;
        if (! filled($orderRef)) {
            return;
        }

        $query->where('order_ref', 'like', '%'.$orderRef.'%');
    }
}