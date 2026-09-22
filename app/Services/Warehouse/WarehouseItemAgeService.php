<?php

namespace App\Services\Warehouse;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class WarehouseItemAgeService
{
    public const PER_PAGE = 1000;

    /**
     * @return LengthAwarePaginator<int, Item>
     */
    public function paginate(Addrbook $warehouse, Request $request, ?User $user): LengthAwarePaginator
    {
        $warehouseId = (int) $warehouse->id;
        $inboundSub = $this->lastInboundSubquery($warehouseId, $user);

        $query = Item::query()
            ->join('warehouse_item as wi', function ($join) use ($warehouseId): void {
                $join->on('wi.item_id', '=', 'items.id')
                    ->where('wi.warehouse_id', '=', $warehouseId);
            })
            ->leftJoinSub($inboundSub, 'inbound', 'inbound.item_id', '=', 'items.id')
            ->select([
                'items.id',
                'items.name',
                'items.code',
                'items.type',
            ])
            ->selectRaw('wi.quantity as warehouse_qty')
            ->selectRaw('inbound.last_inbound_date as last_inbound_date');

        if ($request->input('show0') !== 'show') {
            $query->where('wi.quantity', '>=', 1);
        }

        $this->applySort($query, (string) $request->input('sort', 'agedesc'));

        $perPage = self::PER_PAGE;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<int, array{last_inbound_date: ?string, age_days: ?int}>
     */
    public function decorateRows(iterable $items, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $map = [];

        foreach ($items as $item) {
            $rawDate = $item->last_inbound_date ?? null;
            if ($rawDate === null || $rawDate === '' || $rawDate === '0000-00-00') {
                $map[(int) $item->id] = [
                    'last_inbound_date' => null,
                    'age_days' => null,
                ];

                continue;
            }

            $inbound = Carbon::parse($rawDate)->startOfDay();
            $map[(int) $item->id] = [
                'last_inbound_date' => $inbound->toDateString(),
                'age_days' => (int) $inbound->diffInDays($today),
            ];
        }

        return $map;
    }

    private function lastInboundSubquery(int $warehouseId, ?User $user): Builder
    {
        return $this->inboundDetailBase($warehouseId, $user)
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id')
            ->selectRaw('MAX(transaction_details.date) as last_inbound_date');
    }

    private function inboundDetailBase(int $warehouseId, ?User $user): Builder
    {
        $buy = Transaction::TYPE_BUY;
        $move = Transaction::TYPE_MOVE;

        return TransactionDetail::query()
            ->whereIn('transaction_details.transaction_type', [$buy, $move])
            ->whereNotNull('transaction_details.date')
            ->where('transaction_details.date', '!=', '0000-00-00')
            ->where(function (Builder $receiver) use ($warehouseId): void {
                $receiver
                    ->where('transaction_details.receiver_id', $warehouseId)
                    ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('receiver_id', $warehouseId));
            })
            ->whereHas('transaction', function (Builder $transaction) use ($user): void {
                $transaction
                    ->visibleToUser($user)
                    ->where('status', Transaction::STATUS_COMPLETED);
            });
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'ageasc' => $query
                ->orderByRaw('CASE WHEN inbound.last_inbound_date IS NULL OR inbound.last_inbound_date = ? THEN 1 ELSE 0 END', ['0000-00-00'])
                ->orderByDesc('inbound.last_inbound_date'),
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
                ->orderByRaw('CASE WHEN inbound.last_inbound_date IS NULL OR inbound.last_inbound_date = ? THEN 0 ELSE 1 END', ['0000-00-00'])
                ->orderBy('inbound.last_inbound_date'),
        };
    }

    public function resolvePerPage(Request $request): int
    {
        return self::PER_PAGE;
    }
}
