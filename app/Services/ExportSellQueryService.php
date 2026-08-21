<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExportSellQueryService
{
    /**
     * @return array<int, string>
     */
    public function typeOptions(): array
    {
        return [
            Transaction::TYPE_SELL => 'Sell',
            Transaction::TYPE_RETURN => 'Return',
        ];
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 100);

        return in_array($perPage, [100, 200, 300], true) ? $perPage : 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return $request->only([
            'from',
            'to',
            'type',
            'invoice',
            'item_id',
            'item_code',
            'qty_min',
            'qty_max',
            'discount_min',
            'discount_max',
            'subtotal_min',
            'subtotal_max',
            'sender',
            'receiver',
            'per_page',
        ]);
    }

    public function buildQuery(Request $request, ?User $user): Builder
    {
        $type = $request->input('type', (string) Transaction::TYPE_SELL);

        return TransactionDetail::query()
            ->with([
                'transaction',
                'item',
                'sender',
                'receiver',
            ])
            ->visibleToUser($user)
            ->when(
                $type !== '' && $type !== null,
                fn (Builder $q) => $q->where('transaction_details.transaction_type', (int) $type),
                fn (Builder $q) => $q->whereIn('transaction_details.transaction_type', [
                    Transaction::TYPE_SELL,
                    Transaction::TYPE_RETURN,
                ]),
            )
            ->when($request->input('from'), fn (Builder $q, $v) => $q->whereDate('transaction_details.date', '>=', $v))
            ->when($request->input('to'), fn (Builder $q, $v) => $q->whereDate('transaction_details.date', '<=', $v))
            ->when($request->input('invoice'), function (Builder $q, $v) {
                $pattern = LikeSearch::contains((string) $v);

                return $q->whereHas('transaction', fn (Builder $tq) => $tq->where('invoice', 'like', $pattern));
            })
            ->when($request->input('item_id'), fn (Builder $q, $v) => $q->where('transaction_details.item_id', (int) $v))
            ->when($request->input('item_code'), function (Builder $q, $v) {
                $pattern = LikeSearch::contains((string) $v);

                return $q->whereHas('item', fn (Builder $iq) => $iq
                    ->where('items.code', 'like', $pattern)
                    ->orWhere('items.legacy_code', 'like', $pattern));
            })
            ->when($request->input('qty_min'), fn (Builder $q, $v) => $q->where('transaction_details.quantity', '>=', $v))
            ->when($request->input('qty_max'), fn (Builder $q, $v) => $q->where('transaction_details.quantity', '<=', $v))
            ->when($request->input('discount_min'), fn (Builder $q, $v) => $q->where('transaction_details.discount', '>=', $v))
            ->when($request->input('discount_max'), fn (Builder $q, $v) => $q->where('transaction_details.discount', '<=', $v))
            ->when($request->input('subtotal_min'), fn (Builder $q, $v) => $q->where('transaction_details.total', '>=', $v))
            ->when($request->input('subtotal_max'), fn (Builder $q, $v) => $q->where('transaction_details.total', '<=', $v))
            ->when($request->input('sender'), function (Builder $q, $v) {
                $term = trim((string) $v);
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $q->where('transaction_details.sender_id', (int) $term);
                }

                $pattern = LikeSearch::contains($term);

                return $q->whereHas('sender', fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern));
            })
            ->when($request->input('receiver'), function (Builder $q, $v) {
                $term = trim((string) $v);
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $q->where('transaction_details.receiver_id', (int) $term);
                }

                $pattern = LikeSearch::contains($term);

                return $q->whereHas('receiver', fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern));
            })
            ->orderByDesc('transaction_details.date')
            ->orderByDesc('transaction_details.transaction_id')
            ->orderByDesc('transaction_details.id');
    }
}
