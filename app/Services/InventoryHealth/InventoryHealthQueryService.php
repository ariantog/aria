<?php

namespace App\Services\InventoryHealth;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryHealthQueryService
{
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

        return [
            'from' => $resolved['period_from'],
            'to' => $resolved['period_to'],
            'type' => $request->query('type', ''),
            'invoice' => $request->query('invoice', ''),
            'item_id' => $request->query('item_id', ''),
            'qty_min' => $request->query('qty_min', ''),
            'qty_max' => $request->query('qty_max', ''),
            'sender' => $request->query('sender', ''),
            'receiver' => $request->query('receiver', ''),
            'status' => $request->query('status', ''),
            'per_page' => $this->resolvePerPage($request),
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

    public function paginate(Request $request, ?User $user): LengthAwarePaginator
    {
        $windows = $this->resolveWindows($request);
        $perPage = $this->resolvePerPage($request);
        $mode = $this->activityMode($request);
        $status = (string) $request->query('status', '');
        $validStatuses = array_filter(array_keys(InventoryHealthClassifier::statusOptions()));

        $sales = $this->salesSubquery($request, $user, $windows);
        $stock = $this->stockSubquery($request);

        $query = Item::query()
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
            ->orderBy('items.name');

        $rows = $query->get();

        $decorated = $rows->map(function (Item $item) use ($windows, $mode) {
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
        });

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
            ->whereHas('transaction', function (Builder $transaction) use ($user, $request) {
                $transaction
                    ->visibleToUser($user)
                    ->where('status', Transaction::STATUS_COMPLETED);

                if ($request->filled('invoice')) {
                    $transaction->where('invoice', 'like', LikeSearch::contains((string) $request->query('invoice')));
                }
            })
            ->tap(fn (Builder $q) => $this->applySenderConstraint($q, $request))
            ->when(
                $request->filled('receiver'),
                fn (Builder $q) => $this->applyPartyFilter($q, 'receiver', (string) $request->query('receiver')),
            )
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
        $warehouseId = $this->warehouseIdFromSender($request);

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

    private function applySenderConstraint(Builder $query, Request $request): Builder
    {
        $warehouseId = $this->warehouseIdFromSender($request);
        if ($warehouseId !== null) {
            return $this->applyWarehouseActivityFilter($query, $warehouseId);
        }

        if ($request->filled('sender')) {
            return $this->applyPartyFilter($query, 'sender', (string) $request->query('sender'));
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

    private function applyPartyFilter(Builder $query, string $role, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $detailColumn = $role === 'sender' ? 'transaction_details.sender_id' : 'transaction_details.receiver_id';
        $transactionColumn = $role === 'sender' ? 'sender_id' : 'receiver_id';

        if (ctype_digit($term)) {
            $partyId = (int) $term;

            return $query->where(function (Builder $partyQuery) use ($detailColumn, $transactionColumn, $partyId) {
                $partyQuery
                    ->where($detailColumn, $partyId)
                    ->orWhereHas('transaction', fn (Builder $tq) => $tq->where($transactionColumn, $partyId));
            });
        }

        $pattern = LikeSearch::contains($term);
        $transactionRelation = 'transaction.'.$role;

        return $query->where(function (Builder $partyQuery) use ($role, $transactionRelation, $pattern) {
            $partyQuery
                ->whereHas($role, fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern))
                ->orWhereHas($transactionRelation, fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern));
        });
    }

    private function warehouseIdFromSender(Request $request): ?int
    {
        $term = trim((string) $request->query('sender', ''));
        if ($term === '' || ! ctype_digit($term)) {
            return null;
        }

        $addrbook = Addrbook::query()->find((int) $term);
        if (! $addrbook || ! Addrbook::typeIsWarehouse((int) $addrbook->type)) {
            return null;
        }

        return (int) $addrbook->id;
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
