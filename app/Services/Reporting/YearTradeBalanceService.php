<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class YearTradeBalanceService
{
    /**
     * Inclusive [start, end] for the selected reporting year through as-of.
     * Never starts before the reporting cutover (2025-01-01).
     *
     * @return array{0: string, 1: string}
     */
    public function yearRange(int $year, Carbon $asOf): array
    {
        $cutover = Carbon::parse((string) config('reporting.cutover_date'))->startOfDay();
        $start = Carbon::create($year, 1, 1)->startOfDay();
        if ($start->lt($cutover)) {
            $start = $cutover->copy();
        }

        $end = $asOf->copy()->startOfDay();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * Supplier Umum contact ids (name contains the configured needle).
     *
     * @return list<int>
     */
    public function supplierUmumIds(): array
    {
        $needle = $this->supplierUmumNeedle();

        return Addrbook::query()
            ->withTrashed()
            ->where('type', Addrbook::TYPE_SUPPLIER)
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Outstanding piutang for the year: sell − cash in per customer/reseller.
     * Does not read addrbook_stats or transaction running balances.
     *
     * @return Collection<int, object{
     *     customer_id: int,
     *     customer_type: int,
     *     balance: float,
     *     sell: float,
     *     cash_in: float,
     *     reporting_entity_id: int|null,
     *     name: string|null,
     *     is_internal_lending: bool,
     *     is_active_in_reports: bool
     * }>
     */
    public function receivables(string $start, string $end): Collection
    {
        $partyTypes = [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER];
        $sells = $this->sumByContact(
            Transaction::TYPE_SELL,
            'receiver',
            $partyTypes,
            $start,
            $end,
        );
        $cashIns = $this->sumByContact(
            Transaction::TYPE_CASH_IN,
            'sender',
            $partyTypes,
            $start,
            $end,
        );

        $ids = collect($sells)->keys()
            ->merge(collect($cashIns)->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        $meta = $this->contactMeta($ids);
        $bankEntities = $this->bankEntityMap();

        return collect($ids)
            ->map(function (int $id) use ($sells, $cashIns, $meta, $bankEntities) {
                $info = $meta->get($id);
                $type = (int) ($info['type'] ?? Addrbook::TYPE_CUSTOMER);
                $sell = (float) ($sells[$id] ?? 0);
                $cashIn = (float) ($cashIns[$id] ?? 0);

                return (object) [
                    'customer_id' => $id,
                    'customer_type' => $type,
                    'balance' => round($sell - $cashIn, 2),
                    'sell' => $sell,
                    'cash_in' => $cashIn,
                    'reporting_entity_id' => $this->resolveEntityId($id, $type, $info, $bankEntities),
                    'name' => $info['name'] ?? null,
                    'is_internal_lending' => (bool) ($info['is_internal_lending'] ?? false),
                    'is_active_in_reports' => $info['is_active_in_reports'] !== false,
                ];
            })
            ->filter(fn (object $row) => $row->balance > 0.004)
            ->values();
    }

    /**
     * Hutang usaha for the year: Buy totals from Supplier Umum contacts.
     * Does not read addrbook_stats or transaction running balances.
     *
     * @return Collection<int, object{
     *     customer_id: int,
     *     customer_type: int,
     *     balance: float,
     *     buy: float,
     *     reporting_entity_id: int|null,
     *     name: string|null,
     *     is_internal_lending: bool,
     *     is_active_in_reports: bool
     * }>
     */
    public function payables(string $start, string $end): Collection
    {
        $supplierIds = $this->supplierUmumIds();
        if ($supplierIds === []) {
            return collect();
        }

        $buys = $this->sumByContact(
            Transaction::TYPE_BUY,
            'sender',
            [Addrbook::TYPE_SUPPLIER],
            $start,
            $end,
            $supplierIds,
        );

        $ids = collect($buys)->keys()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return collect();
        }

        $meta = $this->contactMeta($ids);
        $bankEntities = $this->bankEntityMap();

        return collect($ids)
            ->map(function (int $id) use ($buys, $meta, $bankEntities) {
                $info = $meta->get($id);
                $type = (int) ($info['type'] ?? Addrbook::TYPE_SUPPLIER);
                $buy = (float) ($buys[$id] ?? 0);

                return (object) [
                    'customer_id' => $id,
                    'customer_type' => $type,
                    'balance' => $buy,
                    'buy' => $buy,
                    'reporting_entity_id' => $this->resolveEntityId($id, $type, $info, $bankEntities),
                    'name' => $info['name'] ?? null,
                    'is_internal_lending' => (bool) ($info['is_internal_lending'] ?? false),
                    'is_active_in_reports' => $info['is_active_in_reports'] !== false,
                ];
            })
            ->filter(fn (object $row) => $row->balance > 0.004)
            ->values();
    }

    /**
     * @param  list<int>  $partyTypes
     * @param  list<int>|null  $restrictIds
     * @return array<int, float>
     */
    private function sumByContact(
        int $transactionType,
        string $side,
        array $partyTypes,
        string $start,
        string $end,
        ?array $restrictIds = null,
    ): array {
        if ($restrictIds !== null && $restrictIds === []) {
            return [];
        }

        $idColumn = $side === 'sender' ? 'sender_id' : 'receiver_id';
        $typeColumn = $side === 'sender' ? 'sender_type' : 'receiver_type';
        $amountSql = 'SUM(ABS(CASE WHEN COALESCE(real_total, 0) = 0 THEN total ELSE real_total END)) as amount';

        $rows = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', $transactionType)
            ->whereIn($typeColumn, $partyTypes)
            ->where($idColumn, '>', 0)
            ->whereBetween('date', [$start, $end])
            ->when($restrictIds !== null, fn ($query) => $query->whereIn($idColumn, $restrictIds))
            ->selectRaw($idColumn.' as contact_id, '.$amountSql)
            ->groupBy($idColumn)
            ->get();

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->contact_id => (float) $row->amount])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array{name: string|null, type: int, is_internal_lending: bool, is_active_in_reports: bool, default_bank_id: int|null}>
     */
    private function contactMeta(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Addrbook::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'type', 'is_internal_lending', 'is_active_in_reports', 'default_bank_id'])
            ->mapWithKeys(fn (Addrbook $contact) => [
                (int) $contact->id => [
                    'name' => $contact->name,
                    'type' => (int) $contact->type,
                    'is_internal_lending' => (bool) $contact->is_internal_lending,
                    'is_active_in_reports' => $contact->is_active_in_reports !== false,
                    'default_bank_id' => $contact->default_bank_id ? (int) $contact->default_bank_id : null,
                ],
            ]);
    }

    /**
     * @return array<int, int>
     */
    private function bankEntityMap(): array
    {
        return DB::table('reporting_entity_banks')
            ->where('is_active', true)
            ->pluck('reporting_entity_id', 'bank_id')
            ->mapWithKeys(fn ($entityId, $bankId) => [(int) $bankId => (int) $entityId])
            ->all();
    }

    /**
     * @param  array{name: string|null, type: int, is_internal_lending: bool, is_active_in_reports: bool, default_bank_id: int|null}|null  $meta
     * @param  array<int, int>  $bankEntities
     */
    private function resolveEntityId(int $customerId, int $type, ?array $meta, array $bankEntities): ?int
    {
        if ($type === Addrbook::TYPE_BANK) {
            return $bankEntities[$customerId] ?? null;
        }

        $defaultBankId = $meta['default_bank_id'] ?? null;
        if ($defaultBankId && isset($bankEntities[$defaultBankId])) {
            return $bankEntities[$defaultBankId];
        }

        return null;
    }

    private function supplierUmumNeedle(): string
    {
        return mb_strtolower(trim((string) config('reporting.supplier_umum_name_needle', 'supplier umum')));
    }
}
