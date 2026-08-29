<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExportSellQueryService
{
    /**
     * @return array<int>
     */
    public function includedTransactionTypes(): array
    {
        return array_values(array_filter(
            array_column(Transaction::getTypes(), 'id'),
            fn (int $id) => ! in_array($id, [
                Transaction::TYPE_CASH_IN,
                Transaction::TYPE_CASH_OUT,
            ], true),
        ));
    }

    /**
     * @return array<int|string, string>
     */
    public function typeOptions(): array
    {
        $options = ['' => 'All types'];

        foreach ($this->includedTransactionTypes() as $typeId) {
            $options[$typeId] = Transaction::typeLabel($typeId);
        }

        return $options;
    }

    /**
     * Addrbook types allowed in export-sell sender/receiver filters.
     *
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
        $perPage = (int) $request->input('per_page', 100);

        return in_array($perPage, [100, 200, 300], true) ? $perPage : 100;
    }

    /**
     * Optional transaction-header columns that can be toggled on the export-sell table.
     *
     * @return list<string>
     */
    public function optionalTransactionColumnKeys(): array
    {
        return ['adjustment', 'discount', 'total', 'description'];
    }

    /**
     * @return array<string, string>
     */
    public function optionalTransactionColumnLabels(): array
    {
        return [
            'adjustment' => 'Adjustment',
            'discount' => 'Inv. Discount',
            'total' => 'Tx Total',
            'description' => 'Description',
        ];
    }

    /**
     * @return list<string>
     */
    public function visibleTransactionColumnsFromRequest(Request $request): array
    {
        $visible = [];

        foreach ($this->optionalTransactionColumnKeys() as $key) {
            if ($request->boolean('show_tx_'.$key)) {
                $visible[] = $key;
            }
        }

        return $visible;
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
            'sender',
            'receiver',
            'per_page',
        ]);
    }

    public function buildQuery(Request $request, ?User $user, ?int $addrbookId = null): Builder
    {
        $type = $request->input('type');
        $includedTypes = $this->includedTransactionTypes();

        return TransactionDetail::query()
            ->with([
                'transaction',
                'transaction.sender',
                'transaction.receiver',
                'item',
                'sender',
                'receiver',
            ])
            ->visibleToUser($user)
            ->where(function (Builder $typeQuery) use ($includedTypes) {
                $typeQuery
                    ->whereIn('transaction_details.transaction_type', $includedTypes)
                    ->orWhereHas('transaction', fn (Builder $tq) => $tq->whereIn('type', $includedTypes));
            })
            ->when(
                $addrbookId !== null,
                fn (Builder $q) => $q->where(function (Builder $partyQuery) use ($addrbookId) {
                    $this->applyPartyIdFilter($partyQuery, 'sender', $addrbookId)
                        ->orWhere(function (Builder $receiverQuery) use ($addrbookId) {
                            $this->applyPartyIdFilter($receiverQuery, 'receiver', $addrbookId);
                        });
                }),
            )
            ->when(
                $type !== '' && $type !== null,
                fn (Builder $q) => $q->where(function (Builder $typeQuery) use ($type) {
                    $typeQuery
                        ->where('transaction_details.transaction_type', (int) $type)
                        ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('type', (int) $type));
                }),
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
            ->when($addrbookId === null && $request->filled('sender'), function (Builder $q) use ($request) {
                $term = trim((string) $request->input('sender'));
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $this->applyPartyIdFilter($q, 'sender', (int) $term);
                }

                return $this->applyPartyNameFilter($q, 'sender', $term);
            })
            ->when($addrbookId === null && $request->filled('receiver'), function (Builder $q) use ($request) {
                $term = trim((string) $request->input('receiver'));
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $this->applyPartyIdFilter($q, 'receiver', (int) $term);
                }

                return $this->applyPartyNameFilter($q, 'receiver', $term);
            })
            ->orderBy('transaction_details.date')
            ->orderBy('transaction_details.transaction_id')
            ->orderBy('transaction_details.id');
    }

    private function applyPartyIdFilter(Builder $query, string $role, int $partyId): Builder
    {
        $detailColumn = $role === 'sender' ? 'transaction_details.sender_id' : 'transaction_details.receiver_id';
        $transactionColumn = $role === 'sender' ? 'sender_id' : 'receiver_id';

        return $query->where(function (Builder $partyQuery) use ($detailColumn, $transactionColumn, $partyId) {
            $partyQuery
                ->where($detailColumn, $partyId)
                ->orWhereHas('transaction', fn (Builder $tq) => $tq->where($transactionColumn, $partyId));
        });
    }

    private function applyPartyNameFilter(Builder $query, string $role, string $term): Builder
    {
        $pattern = LikeSearch::contains($term);
        $transactionRelation = 'transaction.'.$role;

        return $query->where(function (Builder $partyQuery) use ($role, $transactionRelation, $pattern) {
            $partyQuery
                ->whereHas($role, fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern))
                ->orWhereHas($transactionRelation, fn (Builder $sq) => $sq->where('customers.name', 'like', $pattern));
        });
    }
}
