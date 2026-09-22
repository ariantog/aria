<?php

namespace App\Services\Warehouse;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseItemAgeService
{
    public const PER_PAGE = 1000;

    public const DEFAULT_STALE_MONTHS = 6;

    public const MAX_STALE_MONTHS = 36;

    /**
     * @return LengthAwarePaginator<int, Item>
     */
    public function paginate(Addrbook $warehouse, Request $request, ?User $user): LengthAwarePaginator
    {
        $warehouseId = (int) $warehouse->id;
        $staleMonths = $this->resolveStaleMonths($request);
        $staleCutoff = $this->staleCutoffDate($staleMonths);

        $inboundSub = $this->lastInboundSubquery($warehouseId, $user);
        $soldSub = $this->lastSoldSubquery($warehouseId, $user);

        $query = Item::query()
            ->join('warehouse_item as wi', function ($join) use ($warehouseId): void {
                $join->on('wi.item_id', '=', 'items.id')
                    ->where('wi.warehouse_id', '=', $warehouseId);
            })
            ->leftJoinSub($inboundSub, 'inbound', 'inbound.item_id', '=', 'items.id')
            ->leftJoinSub($soldSub, 'sold', 'sold.item_id', '=', 'items.id')
            ->select([
                'items.id',
                'items.name',
                'items.code',
                'items.type',
            ])
            ->selectRaw('wi.quantity as warehouse_qty')
            ->selectRaw('inbound.last_inbound_date as last_inbound_date')
            ->selectRaw('sold.last_sold_date as last_sold_date');

        if ($request->input('show0') !== 'show') {
            $query->where('wi.quantity', '>=', 1);
        }

        if ($request->boolean('stale_only')) {
            $cutoff = $staleCutoff->toDateString();
            $query->whereNotNull('inbound.last_inbound_date')
                ->where('inbound.last_inbound_date', '!=', '0000-00-00')
                ->whereDate('inbound.last_inbound_date', '<=', $cutoff)
                ->where(function (Builder $noRecentSell) use ($cutoff): void {
                    $noRecentSell
                        ->whereNull('sold.last_sold_date')
                        ->orWhere('sold.last_sold_date', '=', '0000-00-00')
                        ->orWhereDate('sold.last_sold_date', '<', $cutoff);
                });
        }

        $this->applySort($query, (string) $request->input('sort', 'staledesc'), $staleCutoff);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    public function resolveStaleMonths(Request $request): int
    {
        $raw = $request->query('stale_months', (string) self::DEFAULT_STALE_MONTHS);
        if (! is_numeric($raw)) {
            return self::DEFAULT_STALE_MONTHS;
        }

        $months = (int) $raw;

        return max(1, min(self::MAX_STALE_MONTHS, $months));
    }

    public function staleCutoffDate(int $staleMonths, ?Carbon $today = null): Carbon
    {
        $base = ($today ?? Carbon::now())->copy()->startOfDay();

        return $base->subMonthsNoOverflow($staleMonths);
    }

    /**
     * @return array<int, array{
     *     last_inbound_date: ?string,
     *     inbound_age_days: ?int,
     *     last_sold_date: ?string,
     *     days_since_sell: ?int,
     *     no_sell_in_window: bool,
     *     stale_unsold: bool
     * }>
     */
    public function decorateRows(iterable $items, ?Carbon $today = null, int $staleMonths = self::DEFAULT_STALE_MONTHS): array
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        $staleCutoff = $this->staleCutoffDate($staleMonths, $today);
        $map = [];

        foreach ($items as $item) {
            $inboundDate = $this->normalizeDate($item->last_inbound_date ?? null);
            $soldDate = $this->normalizeDate($item->last_sold_date ?? null);

            $inboundAgeDays = $inboundDate !== null ? (int) $inboundDate->diffInDays($today) : null;
            $daysSinceSell = $soldDate !== null ? (int) $soldDate->diffInDays($today) : null;

            $noSellInWindow = $soldDate === null || $soldDate->lessThan($staleCutoff);
            $inboundOldEnough = $inboundDate !== null && $inboundDate->lessThanOrEqualTo($staleCutoff);

            $staleUnsold = $inboundOldEnough && $noSellInWindow;

            $map[(int) $item->id] = [
                'last_inbound_date' => $inboundDate?->toDateString(),
                'inbound_age_days' => $inboundAgeDays,
                'last_sold_date' => $soldDate?->toDateString(),
                'days_since_sell' => $daysSinceSell,
                'no_sell_in_window' => $noSellInWindow,
                'stale_unsold' => $staleUnsold,
            ];
        }

        return $map;
    }

    private function normalizeDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00') {
            return null;
        }

        return Carbon::parse($raw)->startOfDay();
    }

    private function lastInboundSubquery(int $warehouseId, ?User $user): QueryBuilder
    {
        $inboundTypes = [Transaction::TYPE_BUY, Transaction::TYPE_MOVE, Transaction::TYPE_PRODUCTION];

        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->whereIn('td.transaction_id', $this->visibleTransactionIds($user))
            ->whereNotNull('td.date')
            ->where('td.date', '!=', '0000-00-00')
            ->where(function (QueryBuilder $typeMatch) use ($inboundTypes): void {
                $typeMatch
                    ->whereIn('t.type', $inboundTypes)
                    ->orWhereIn('td.transaction_type', $inboundTypes);
            })
            ->whereRaw('COALESCE(NULLIF(td.receiver_id, 0), t.receiver_id) = ?', [$warehouseId])
            ->groupBy('td.item_id')
            ->selectRaw('td.item_id as item_id, MAX(td.date) as last_inbound_date');
    }

    private function lastSoldSubquery(int $warehouseId, ?User $user): QueryBuilder
    {
        $sell = Transaction::TYPE_SELL;

        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->whereIn('td.transaction_id', $this->visibleTransactionIds($user))
            ->whereNotNull('td.date')
            ->where('td.date', '!=', '0000-00-00')
            ->where(function (QueryBuilder $typeMatch) use ($sell): void {
                $typeMatch
                    ->where('t.type', $sell)
                    ->orWhere('td.transaction_type', $sell);
            })
            ->whereRaw('COALESCE(NULLIF(td.sender_id, 0), t.sender_id) = ?', [$warehouseId])
            ->groupBy('td.item_id')
            ->selectRaw('td.item_id as item_id, MAX(td.date) as last_sold_date');
    }

    private function visibleTransactionIds(?User $user): Builder
    {
        return Transaction::query()
            ->visibleToUser($user)
            ->countsInReporting()
            ->select('transactions.id');
    }

    private function applySort(Builder $query, string $sort, Carbon $staleCutoff): void
    {
        $cutoff = $staleCutoff->toDateString();

        match ($sort) {
            'staleasc' => $query
                ->orderByRaw(
                    'CASE WHEN inbound.last_inbound_date IS NOT NULL AND inbound.last_inbound_date <= ? AND (sold.last_sold_date IS NULL OR sold.last_sold_date = ? OR sold.last_sold_date < ?) THEN 1 ELSE 0 END',
                    [$cutoff, '0000-00-00', $cutoff],
                )
                ->orderBy('inbound.last_inbound_date'),
            'soldasc' => $query
                ->orderByRaw('CASE WHEN sold.last_sold_date IS NULL OR sold.last_sold_date = ? THEN 1 ELSE 0 END', ['0000-00-00'])
                ->orderByDesc('sold.last_sold_date'),
            'solddesc' => $query
                ->orderByRaw('CASE WHEN sold.last_sold_date IS NULL OR sold.last_sold_date = ? THEN 1 ELSE 0 END', ['0000-00-00'])
                ->orderBy('sold.last_sold_date'),
            'ageasc' => $query
                ->orderByRaw('CASE WHEN inbound.last_inbound_date IS NULL OR inbound.last_inbound_date = ? THEN 1 ELSE 0 END', ['0000-00-00'])
                ->orderByDesc('inbound.last_inbound_date'),
            'agedesc' => $query
                ->orderByRaw('CASE WHEN inbound.last_inbound_date IS NULL OR inbound.last_inbound_date = ? THEN 0 ELSE 1 END', ['0000-00-00'])
                ->orderBy('inbound.last_inbound_date'),
            'codedesc' => $query->orderBy('items.code', 'desc'),
            'codeasc' => $query->orderBy('items.code', 'asc'),
            'namedesc' => $query->orderBy('items.name', 'desc'),
            'nameasc' => $query->orderBy('items.name', 'asc'),
            'qtydesc' => $query->orderByDesc('wi.quantity'),
            'qtyasc' => $query->orderBy('wi.quantity'),
            'inbounddesc' => $query->orderByDesc('inbound.last_inbound_date'),
            'inboundasc' => $query
                ->orderByRaw('CASE WHEN inbound.last_inbound_date IS NULL OR inbound.last_inbound_date = ? THEN 1 ELSE 0 END', ['0000-00-00'])
                ->orderBy('inbound.last_inbound_date'),
            default => $query
                ->orderByRaw(
                    'CASE WHEN inbound.last_inbound_date IS NOT NULL AND inbound.last_inbound_date <= ? AND (sold.last_sold_date IS NULL OR sold.last_sold_date = ? OR sold.last_sold_date < ?) THEN 0 ELSE 1 END',
                    [$cutoff, '0000-00-00', $cutoff],
                )
                ->orderBy('inbound.last_inbound_date'),
        };
    }

    public function resolvePerPage(Request $request): int
    {
        return self::PER_PAGE;
    }
}
