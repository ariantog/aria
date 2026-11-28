<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ExportSellQueryService
{
    public const DEFAULT_RANGE_DAYS = 90;

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
        $filters = [];
        foreach ([
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
        ] as $key) {
            if (! $request->query->has($key)) {
                continue;
            }
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Filters with a default recent date window when the user has not narrowed the report.
     *
     * @return array<string, mixed>
     */
    public function effectiveFilters(Request $request): array
    {
        $filters = $this->filtersFromRequest($request);

        if ($this->shouldApplyDefaultDateWindow($request)) {
            if (($filters['from'] ?? '') === '') {
                $filters['from'] = now()->subDays(self::DEFAULT_RANGE_DAYS)->toDateString();
            }
            if (($filters['to'] ?? '') === '') {
                $filters['to'] = now()->toDateString();
            }
        }

        return $filters;
    }

    public function buildQuery(Request $request, ?User $user, ?int $addrbookId = null): Builder
    {
        $filters = $this->effectiveFilters($request);

        return $this->applyFilters($filters, $user, $addrbookId)
            ->with([
                'transaction',
                'transaction.sender',
                'transaction.receiver',
                'item',
                'sender',
                'receiver',
            ])
            ->leftJoin('transactions as export_sell_txn_sort', 'export_sell_txn_sort.id', '=', 'transaction_details.transaction_id')
            ->leftJoin('items as export_sell_item_sort', 'export_sell_item_sort.id', '=', 'transaction_details.item_id')
            ->select('transaction_details.*')
            ->orderByDesc('transaction_details.date')
            ->orderByDesc('export_sell_txn_sort.invoice')
            ->orderBy('export_sell_item_sort.code')
            ->orderBy('transaction_details.id');
    }

    /**
     * Paginate export-sell by transaction groups (per_page = transactions per page).
     */
    public function paginateForIndex(Request $request, ?User $user, ?int $addrbookId = null): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);
        $filters = $this->effectiveFilters($request);

        $transactionPage = $this->applyFilters($filters, $user, $addrbookId)
            ->select('transaction_details.transaction_id')
            ->leftJoin('transactions as export_sell_txn_page', 'export_sell_txn_page.id', '=', 'transaction_details.transaction_id')
            ->groupBy('transaction_details.transaction_id', 'export_sell_txn_page.date', 'export_sell_txn_page.invoice', 'export_sell_txn_page.id')
            ->orderByDesc('export_sell_txn_page.date')
            ->orderByDesc('export_sell_txn_page.invoice')
            ->orderByDesc('export_sell_txn_page.id')
            ->paginate($perPage)
            ->withQueryString();

        $transactionIds = $transactionPage->getCollection()->pluck('transaction_id')->map(fn ($id) => (int) $id);

        if ($transactionIds->isEmpty()) {
            return $transactionPage;
        }

        $lines = $this->applyFilters($filters, $user, $addrbookId)
            ->with([
                'transaction',
                'transaction.sender',
                'transaction.receiver',
                'item',
                'sender',
                'receiver',
            ])
            ->leftJoin('transactions as export_sell_txn_sort', 'export_sell_txn_sort.id', '=', 'transaction_details.transaction_id')
            ->leftJoin('items as export_sell_item_sort', 'export_sell_item_sort.id', '=', 'transaction_details.item_id')
            ->whereIn('transaction_details.transaction_id', $transactionIds->all())
            ->select('transaction_details.*')
            ->orderByDesc('transaction_details.date')
            ->orderByDesc('export_sell_txn_sort.invoice')
            ->orderBy('export_sell_item_sort.code')
            ->orderBy('transaction_details.id')
            ->get();

        $transactionPage->setCollection($lines);

        return $transactionPage;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(array $filters, ?User $user, ?int $addrbookId): Builder
    {
        $type = $filters['type'] ?? null;
        $includedTypes = $this->includedTransactionTypes();

        return TransactionDetail::query()
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
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('transaction_details.date', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('transaction_details.date', '<=', $v))
            ->when($filters['invoice'] ?? null, function (Builder $q, $v) {
                $pattern = LikeSearch::contains((string) $v);

                return $q->whereHas('transaction', fn (Builder $tq) => $tq->where('invoice', 'like', $pattern));
            })
            ->when($filters['item_id'] ?? null, fn (Builder $q, $v) => $q->where('transaction_details.item_id', (int) $v))
            ->when($filters['item_code'] ?? null, function (Builder $q, $v) {
                $pattern = LikeSearch::contains((string) $v);

                return $q->whereHas('item', fn (Builder $iq) => $iq
                    ->where('items.code', 'like', $pattern)
                    ->orWhere('items.legacy_code', 'like', $pattern));
            })
            ->when($filters['qty_min'] ?? null, fn (Builder $q, $v) => $q->where('transaction_details.quantity', '>=', $v))
            ->when($filters['qty_max'] ?? null, fn (Builder $q, $v) => $q->where('transaction_details.quantity', '<=', $v))
            ->when($addrbookId === null && filled($filters['sender'] ?? null), function (Builder $q) use ($filters) {
                $term = trim((string) $filters['sender']);
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $this->applyPartyIdFilter($q, 'sender', (int) $term);
                }

                return $this->applyPartyNameFilter($q, 'sender', $term);
            })
            ->when($addrbookId === null && filled($filters['receiver'] ?? null), function (Builder $q) use ($filters) {
                $term = trim((string) $filters['receiver']);
                if ($term === '') {
                    return $q;
                }

                if (ctype_digit($term)) {
                    return $this->applyPartyIdFilter($q, 'receiver', (int) $term);
                }

                return $this->applyPartyNameFilter($q, 'receiver', $term);
            });
    }

    private function queryFilterFilled(Request $request, string $key): bool
    {
        if (! $request->query->has($key)) {
            return false;
        }

        $value = $request->query($key);

        return $value !== null && $value !== '';
    }

    private function shouldApplyDefaultDateWindow(Request $request): bool
    {
        if ($this->queryFilterFilled($request, 'from') || $this->queryFilterFilled($request, 'to')) {
            return false;
        }

        foreach (['invoice', 'item_id', 'item_code', 'sender', 'receiver', 'type', 'qty_min', 'qty_max'] as $key) {
            if ($this->queryFilterFilled($request, $key)) {
                return false;
            }
        }

        return true;
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