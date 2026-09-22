<?php

namespace App\Services\InventoryHealth;

use App\Models\Addrbook;
use App\Models\InventoryHealthSnapshot;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryHealthQueryService
{
    public const DEFAULT_SORT = 'name';

    public const DEFAULT_DIRECTION = 'asc';

    /**
     * @return list<string>
     */
    public static function sortableColumns(): array
    {
        return ['name', 'sold', 'returned', 'net', 'stock', 'cover', 'last_sold', 'status'];
    }

    /**
     * @return array<int|string, string>
     */
    public function typeOptions(): array
    {
        return [
            '' => 'Net (Sell − Return)',
            Transaction::TYPE_SELL => 'Sell only',
            Transaction::TYPE_RETURN => 'Return only',
        ];
    }

    /**
     * @return list<int>
     */
    public function partyTypeIds(): array
    {
        return [
            Addrbook::TYPE_CUSTOMER,
            Addrbook::TYPE_RESELLER,
            Addrbook::TYPE_WAREHOUSE,
            Addrbook::TYPE_V_WAREHOUSE,
        ];
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 100);

        return in_array($perPage, [100, 200, 300], true) ? $perPage : 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        $resolved = $this->resolveWindows($request);
        $sort = $this->resolveSort($request);

        return [
            'from' => $resolved['period_from'],
            'to' => $resolved['period_to'],
            'type' => $request->query('type', ''),
            'item_id' => $request->query('item_id', ''),
            'qty_min' => $request->query('qty_min', ''),
            'qty_max' => $request->query('qty_max', ''),
            'warehouse_id' => $this->warehouseFilterQueryValue($request),
            'status' => $request->query('status', ''),
            'per_page' => $this->resolvePerPage($request),
            'sort' => $sort['column'],
            'direction' => $sort['direction'],
        ];
    }

    /**
     * @return array{column: string, direction: 'asc'|'desc'}
     */
    public function resolveSort(Request $request): array
    {
        $column = (string) $request->query('sort', self::DEFAULT_SORT);
        if (! in_array($column, self::sortableColumns(), true)) {
            $column = self::DEFAULT_SORT;
        }

        $direction = strtolower((string) $request->query('direction', self::DEFAULT_DIRECTION));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::DEFAULT_DIRECTION;
        }

        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    /**
     * @return array{period_from: string, period_to: string, extended_from: string, period_days: int}
     */
    public function resolveWindows(Request $request): array
    {
        $to = $this->parseDate($request->query('to')) ?? now()->toDateString();
        $from = $this->parseDate($request->query('from')) ?? Carbon::parse($to)->subDays(30)->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $periodDays = max(1, (int) Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()));
        $extendedFrom = Carbon::parse($to)->subDays(90)->toDateString();
        if ($from < $extendedFrom) {
            $extendedFrom = $from;
        }

        return [
            'period_from' => $from,
            'period_to' => $to,
            'extended_from' => $extendedFrom,
            'period_days' => $periodDays,
        ];
    }

    /**
     * @return array{source: 'snapshot'|'live', synced_at: ?\Carbon\CarbonInterface, stale: bool, has_snapshots: bool}
     */
    public function pageMeta(Request $request): array
    {
        if (! Schema::hasTable('inventory_health_snapshots')) {
            return [
                'source' => 'live',
                'synced_at' => null,
                'stale' => true,
                'has_snapshots' => false,
            ];
        }

        $syncedAt = app(InventoryHealthSyncService::class)->latestSyncedAt();
        $hasSnapshots = InventoryHealthSnapshot::query()->exists();

        return [
            'source' => $this->canUseSnapshot($request) ? 'snapshot' : 'live',
            'synced_at' => $syncedAt,
            'stale' => $syncedAt ? $syncedAt->lt(now()->subDay()) : true,
            'has_snapshots' => $hasSnapshots,
        ];
    }

    public function canUseSnapshot(Request $request): bool
    {
        if (! $this->snapshotsReady()) {
            return false;
        }

        if ($request->filled('from') || $request->filled('to')) {
            $windows = $this->resolveWindows($request);
            $default = app(InventoryHealthSyncService::class)->windows();

            if ($windows['period_from'] !== $default['period_from'] || $windows['period_to'] !== $default['period_to']) {
                return false;
            }
        }

        return true;
    }

    public function paginate(Request $request, ?User $user): LengthAwarePaginator
    {
        $windows = $this->resolveWindows($request);
        $rows = $this->canUseSnapshot($request)
            ? $this->snapshotItems($request)
            : $this->liveItems($request, $user, $windows);

        return $this->decorateAndPaginate($rows, $request, $windows);
    }

    /**
     * Company-wide inventory health rows using the same snapshot/live paths as the report.
     * Does not apply reporting summary cutover — only warehouse stats and transaction detail windows.
     *
     * @return Collection<int, Item>
     */
    public function companyHealthRows(Request $request, ?User $user): Collection
    {
        $windows = $this->resolveWindows($request);
        $rows = $this->canUseSnapshot($request)
            ? $this->snapshotItems($request)
            : $this->liveItems($request, $user, $windows);

        return $this->decorateRows($rows, $request, $windows);
    }

    private function snapshotsReady(): bool
    {
        return Schema::hasTable('inventory_health_snapshots')
            && InventoryHealthSnapshot::query()->exists();
    }

    /**
     * @return Collection<int, Item>
     */
    /**
     * Snapshot warehouse key: 0 = company rollup, otherwise a gudang id.
     */
    public function resolveSnapshotWarehouseId(Request $request): int
    {
        return $this->resolveWarehouseId($request);
    }

    /**
     * @return array<int, string>
     */
    public function warehouseOptionsForFilter(?User $user = null): array
    {
        return Addrbook::query()
            ->visibleToUser($user)
            ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Query value for warehouse filter (empty string = all gudang / company rollup).
     */
    public function warehouseFilterQueryValue(Request $request): string
    {
        $raw = trim((string) $request->query('warehouse_id', ''));

        if ($raw === '' || $raw === '0') {
            return '';
        }

        if (! ctype_digit($raw)) {
            return '';
        }

        $id = (int) $raw;
        if ($id === InventoryHealthSyncService::COMPANY_WAREHOUSE_ID) {
            return '';
        }

        $addrbook = Addrbook::query()->find($id);
        if (! $addrbook || ! Addrbook::typeIsWarehouse(Addrbook::typeValueFrom($addrbook->type))) {
            return '';
        }

        return (string) $id;
    }

    /**
     * Snapshot / filter warehouse: 0 = all gudang rollup, otherwise one gudang.
     */
    public function resolveWarehouseId(Request $request): int
    {
        $filter = $this->warehouseFilterQueryValue($request);

        return $filter === '' ? InventoryHealthSyncService::COMPANY_WAREHOUSE_ID : (int) $filter;
    }

    private function snapshotItems(Request $request): Collection
    {
        $snapshotWarehouseId = $this->resolveSnapshotWarehouseId($request);

        return Item::query()
            ->join('inventory_health_snapshots as snap', 'snap.item_id', '=', 'items.id')
            ->where('snap.warehouse_id', $snapshotWarehouseId)
            ->where(function (Builder $visible) {
                $visible
                    ->where('snap.current_stock', '>', 0)
                    ->orWhere('snap.sold_period', '>', 0)
                    ->orWhere('snap.returned_period', '>', 0)
                    ->orWhere('snap.sold_extended', '>', 0)
                    ->orWhere('snap.returned_extended', '>', 0);
            })
            ->when(
                $request->filled('item_id') && ctype_digit((string) $request->query('item_id')),
                fn (Builder $q) => $q->where('items.id', (int) $request->query('item_id')),
            )
            ->select([
                'items.id',
                'items.name',
                'items.code',
                'items.type',
            ])
            ->selectRaw('snap.sold_period as sold_period')
            ->selectRaw('snap.returned_period as returned_period')
            ->selectRaw('snap.sold_extended as sold_extended')
            ->selectRaw('snap.returned_extended as returned_extended')
            ->selectRaw('snap.last_sold_at as last_sold_at')
            ->selectRaw('snap.current_stock as current_stock')
            ->orderBy('items.name')
            ->get();
    }

    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     * @return Collection<int, Item>
     */
    private function liveItems(Request $request, ?User $user, array $windows): Collection
    {
        $sales = $this->salesSubquery($request, $user, $windows);
        $stock = $this->stockSubquery($request);

        return Item::query()
            ->leftJoinSub($sales, 'sales', 'sales.item_id', '=', 'items.id')
            ->leftJoinSub($stock, 'stock', 'stock.item_id', '=', 'items.id')
            ->where(function (Builder $visible) {
                $visible
                    ->where('stock.current_stock', '>', 0)
                    ->orWhere('sales.sold_period', '>', 0)
                    ->orWhere('sales.returned_period', '>', 0)
                    ->orWhere('sales.sold_extended', '>', 0)
                    ->orWhere('sales.returned_extended', '>', 0);
            })
            ->when(
                $request->filled('item_id') && ctype_digit((string) $request->query('item_id')),
                fn (Builder $q) => $q->where('items.id', (int) $request->query('item_id')),
            )
            ->select([
                'items.id',
                'items.name',
                'items.code',
                'items.type',
            ])
            ->selectRaw('COALESCE(sales.sold_period, 0) as sold_period')
            ->selectRaw('COALESCE(sales.returned_period, 0) as returned_period')
            ->selectRaw('COALESCE(sales.sold_extended, 0) as sold_extended')
            ->selectRaw('COALESCE(sales.returned_extended, 0) as returned_extended')
            ->selectRaw('sales.last_sold_at as last_sold_at')
            ->selectRaw('COALESCE(stock.current_stock, 0) as current_stock')
            ->orderBy('items.name')
            ->get();
    }

    /**
     * @param  Collection<int, Item>  $rows
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     */
    /**
     * @param  Collection<int, Item>  $rows
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     * @return Collection<int, Item>
     */
    private function decorateRows(Collection $rows, Request $request, array $windows): Collection
    {
        $mode = $this->activityMode($request);

        return $rows->map(function (Item $item) use ($windows, $mode) {
            $activity = $this->activityTotals($item, $mode);
            $health = InventoryHealthClassifier::classify(
                (float) $item->current_stock,
                $activity['period'],
                $activity['extended'],
                $windows['period_days'],
            );

            $item->setAttribute('net_period', $activity['period']);
            $item->setAttribute('net_extended', $activity['extended']);
            $item->setAttribute('health', $health);

            return $item;
        })->values();
    }

    private function decorateAndPaginate(Collection $rows, Request $request, array $windows): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);
        $status = (string) $request->query('status', '');
        $validStatuses = array_filter(array_keys(InventoryHealthClassifier::statusOptions()));

        $decorated = $this->decorateRows($rows, $request, $windows);

        if (in_array($status, $validStatuses, true)) {
            $decorated = $decorated->filter(
                fn (Item $item) => ($item->health['key'] ?? null) === $status
            )->values();
        }

        $qtyMin = $this->optionalFloat($request->query('qty_min'));
        $qtyMax = $this->optionalFloat($request->query('qty_max'));
        if ($qtyMin !== null) {
            $decorated = $decorated->filter(fn (Item $item) => (float) $item->net_period >= $qtyMin)->values();
        }
        if ($qtyMax !== null) {
            $decorated = $decorated->filter(fn (Item $item) => (float) $item->net_period <= $qtyMax)->values();
        }

        $sort = $this->resolveSort($request);
        $decorated = $this->sortRows($decorated, $sort['column'], $sort['direction']);

        $page = max(1, (int) $request->query('page', 1));
        $slice = $decorated->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $decorated->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @param  Collection<int, Item>  $rows
     * @return Collection<int, Item>
     */
    private function sortRows(Collection $rows, string $column, string $direction): Collection
    {
        $desc = $direction === 'desc';
        $statusRank = array_flip(array_values(array_filter(
            array_keys(InventoryHealthClassifier::statusOptions()),
        )));

        return $rows->sort(function (Item $left, Item $right) use ($column, $desc, $statusRank) {
            [$leftValue, $leftEmpty] = $this->sortValue($left, $column, $statusRank);
            [$rightValue, $rightEmpty] = $this->sortValue($right, $column, $statusRank);

            if ($leftEmpty && $rightEmpty) {
                return strcasecmp((string) $left->name, (string) $right->name);
            }
            if ($leftEmpty) {
                return 1;
            }
            if ($rightEmpty) {
                return -1;
            }

            $cmp = $leftValue <=> $rightValue;
            if ($cmp === 0) {
                return strcasecmp((string) $left->name, (string) $right->name);
            }

            return $desc ? -$cmp : $cmp;
        })->values();
    }

    /**
     * @param  array<string, int>  $statusRank
     * @return array{0: mixed, 1: bool}
     */
    private function sortValue(Item $item, string $column, array $statusRank): array
    {
        return match ($column) {
            'sold' => [(float) ($item->sold_period ?? 0), false],
            'returned' => [(float) ($item->returned_period ?? 0), false],
            'net' => [(float) ($item->net_period ?? 0), false],
            'stock' => [(float) ($item->current_stock ?? 0), false],
            'cover' => $this->sortCoverTuple($item),
            'last_sold' => [
                $item->last_sold_at ? Carbon::parse($item->last_sold_at)->timestamp : null,
                $item->last_sold_at === null || $item->last_sold_at === '',
            ],
            'status' => [$statusRank[$item->health['key'] ?? ''] ?? PHP_INT_MAX, false],
            default => [mb_strtolower((string) $item->name), false],
        };
    }

    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     */
    private function salesSubquery(Request $request, ?User $user, array $windows): Builder
    {
        $sell = Transaction::TYPE_SELL;
        $return = Transaction::TYPE_RETURN;
        $types = $this->includedTypes($request);
        $periodFrom = $windows['period_from'];
        $extendedFrom = $windows['extended_from'];
        $periodTo = $windows['period_to'];

        $query = TransactionDetail::query()
            ->whereIn('transaction_details.transaction_type', $types)
            ->whereNotNull('transaction_details.date')
            ->where('transaction_details.date', '!=', '0000-00-00')
            ->whereDate('transaction_details.date', '>=', $extendedFrom)
            ->whereDate('transaction_details.date', '<=', $periodTo)
            ->whereHas('transaction', function (Builder $transaction) use ($user) {
                $transaction
                    ->visibleToUser($user)
                    ->where('status', Transaction::STATUS_COMPLETED);
            })
            ->tap(fn (Builder $q) => $this->applyWarehouseSalesScope($q, $request))
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id')
            ->selectRaw(
                'SUM(CASE WHEN transaction_details.transaction_type = ? AND transaction_details.date >= ? THEN ABS(transaction_details.quantity) ELSE 0 END) as sold_period',
                [$sell, $periodFrom],
            )
            ->selectRaw(
                'SUM(CASE WHEN transaction_details.transaction_type = ? AND transaction_details.date >= ? THEN ABS(transaction_details.quantity) ELSE 0 END) as returned_period',
                [$return, $periodFrom],
            )
            ->selectRaw(
                'SUM(CASE WHEN transaction_details.transaction_type = ? THEN ABS(transaction_details.quantity) ELSE 0 END) as sold_extended',
                [$sell],
            )
            ->selectRaw(
                'SUM(CASE WHEN transaction_details.transaction_type = ? THEN ABS(transaction_details.quantity) ELSE 0 END) as returned_extended',
                [$return],
            )
            ->selectRaw(
                'MAX(CASE WHEN transaction_details.transaction_type = ? THEN transaction_details.date ELSE NULL END) as last_sold_at',
                [$sell],
            );

        return $query;
    }

    private function stockSubquery(Request $request): QueryBuilder
    {
        $warehouseId = $this->resolvePhysicalWarehouseId($request);

        return DB::table('warehouse_item')
            ->select('item_id', DB::raw('COALESCE(SUM(quantity), 0) as current_stock'))
            ->when(
                $warehouseId !== null,
                fn (QueryBuilder $q) => $q->where('warehouse_id', $warehouseId),
                fn (QueryBuilder $q) => $q->whereIn('warehouse_id', Addrbook::query()
                    ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
                    ->select('id')),
            )
            ->groupBy('item_id');
    }

    /**
     * @return list<int>
     */
    private function includedTypes(Request $request): array
    {
        $type = $request->query('type');
        if ((string) $type === (string) Transaction::TYPE_SELL) {
            return [Transaction::TYPE_SELL];
        }
        if ((string) $type === (string) Transaction::TYPE_RETURN) {
            return [Transaction::TYPE_RETURN];
        }

        return [Transaction::TYPE_SELL, Transaction::TYPE_RETURN];
    }

    /**
     * @return 'net'|'sell'|'return'
     */
    private function activityMode(Request $request): string
    {
        $type = $request->query('type');
        if ((string) $type === (string) Transaction::TYPE_SELL) {
            return 'sell';
        }
        if ((string) $type === (string) Transaction::TYPE_RETURN) {
            return 'return';
        }

        return 'net';
    }

    /**
     * @return array{period: float, extended: float}
     */
    private function activityTotals(Item $item, string $mode): array
    {
        $soldPeriod = (float) ($item->sold_period ?? 0);
        $returnedPeriod = (float) ($item->returned_period ?? 0);
        $soldExtended = (float) ($item->sold_extended ?? 0);
        $returnedExtended = (float) ($item->returned_extended ?? 0);

        return match ($mode) {
            'sell' => ['period' => $soldPeriod, 'extended' => $soldExtended],
            'return' => ['period' => $returnedPeriod, 'extended' => $returnedExtended],
            default => [
                'period' => max(0.0, $soldPeriod - $returnedPeriod),
                'extended' => max(0.0, $soldExtended - $returnedExtended),
            ],
        };
    }

    /**
     * @return array{0: mixed, 1: bool}
     */
    private function sortCoverTuple(Item $item): array
    {
        $stock = (float) ($item->current_stock ?? 0);
        $netPeriod = (float) ($item->net_period ?? 0);

        if (InventoryHealthClassifier::coverIsInfinite($stock, $netPeriod)) {
            return [PHP_FLOAT_MAX, false];
        }

        $cover = $item->health['days_of_cover'] ?? null;
        if ($cover === null || $stock <= 0.0) {
            return [null, true];
        }

        return [(float) $cover, false];
    }

    private function resolvePhysicalWarehouseId(Request $request): ?int
    {
        $id = $this->resolveWarehouseId($request);
        if ($id === InventoryHealthSyncService::COMPANY_WAREHOUSE_ID) {
            return null;
        }

        return $id;
    }

    private function applyWarehouseSalesScope(Builder $query, Request $request): Builder
    {
        $warehouseId = $this->resolvePhysicalWarehouseId($request);
        if ($warehouseId !== null) {
            return $this->applyWarehouseActivityFilter($query, $warehouseId);
        }

        return $this->restrictToWarehouseParties($query);
    }

    private function applyWarehouseActivityFilter(Builder $query, int $warehouseId): Builder
    {
        $sell = Transaction::TYPE_SELL;
        $return = Transaction::TYPE_RETURN;

        return $query->where(function (Builder $party) use ($warehouseId, $sell, $return) {
            $party
                ->where(function (Builder $sellQuery) use ($warehouseId, $sell) {
                    $sellQuery
                        ->where('transaction_details.transaction_type', $sell)
                        ->where(function (Builder $sender) use ($warehouseId) {
                            $sender
                                ->where('transaction_details.sender_id', $warehouseId)
                                ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('sender_id', $warehouseId));
                        });
                })
                ->orWhere(function (Builder $returnQuery) use ($warehouseId, $return) {
                    $returnQuery
                        ->where('transaction_details.transaction_type', $return)
                        ->where(function (Builder $receiver) use ($warehouseId) {
                            $receiver
                                ->where('transaction_details.receiver_id', $warehouseId)
                                ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('receiver_id', $warehouseId));
                        });
                });
        });
    }

    private function restrictToWarehouseParties(Builder $query): Builder
    {
        $warehouseTypes = [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE];
        $sell = Transaction::TYPE_SELL;
        $return = Transaction::TYPE_RETURN;

        return $query->where(function (Builder $party) use ($warehouseTypes, $sell, $return) {
            $party
                ->where(function (Builder $sellQuery) use ($warehouseTypes, $sell) {
                    $sellQuery
                        ->where('transaction_details.transaction_type', $sell)
                        ->whereHas('sender', fn (Builder $sender) => $sender->whereIn('type', $warehouseTypes));
                })
                ->orWhere(function (Builder $returnQuery) use ($warehouseTypes, $return) {
                    $returnQuery
                        ->where('transaction_details.transaction_type', $return)
                        ->whereHas('receiver', fn (Builder $receiver) => $receiver->whereIn('type', $warehouseTypes));
                });
        });
    }

    private function parseDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
