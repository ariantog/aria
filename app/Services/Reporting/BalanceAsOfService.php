<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingBalanceSnapshot;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceAsOfService
{
    /**
     * Contact types that appear on the neraca (kas / piutang / hutang).
     *
     * @var list<int>
     */
    public const NERACA_TYPES = [
        Addrbook::TYPE_CUSTOMER,
        Addrbook::TYPE_BANK,
        Addrbook::TYPE_SUPPLIER,
        Addrbook::TYPE_RESELLER,
    ];

    /**
     * Historical balances as of a date. Uses a persisted snapshot when present;
     * otherwise replays sender_balance / receiver_balance from transactions
     * on or before the date. Never reads current addrbook_stats.
     *
     * @return Collection<int, object{
     *     customer_id: int,
     *     customer_type: int,
     *     balance: float,
     *     reporting_entity_id: int|null,
     *     name: string|null,
     *     is_internal_lending: bool,
     *     is_active_in_reports: bool
     * }>
     */
    public function balancesAsOf(Carbon $asOf, bool $persist = true, bool $refresh = false): Collection
    {
        $date = $asOf->toDateString();

        if ($refresh) {
            ReportingBalanceSnapshot::query()->whereDate('as_of_date', $date)->delete();
        }

        if (! $refresh && $this->hasSnapshot($date)) {
            return $this->fromSnapshot($date);
        }

        $replayed = $this->replayAsOf($asOf);

        if ($persist) {
            $this->persistSnapshot($asOf, $replayed);
        }

        return $replayed;
    }

    public function hasSnapshot(string $date): bool
    {
        return ReportingBalanceSnapshot::query()->whereDate('as_of_date', $date)->exists();
    }

    /**
     * @return Collection<int, object>
     */
    public function fromSnapshot(string $date): Collection
    {
        $rows = ReportingBalanceSnapshot::query()
            ->whereDate('as_of_date', $date)
            ->get(['customer_id', 'customer_type', 'balance', 'reporting_entity_id']);

        $contacts = $this->contactMeta($rows->pluck('customer_id')->all());

        return $rows->map(function (ReportingBalanceSnapshot $row) use ($contacts) {
            $meta = $contacts->get((int) $row->customer_id);

            return (object) [
                'customer_id' => (int) $row->customer_id,
                'customer_type' => (int) $row->customer_type,
                'balance' => (float) $row->balance,
                'reporting_entity_id' => $row->reporting_entity_id !== null ? (int) $row->reporting_entity_id : null,
                'name' => $meta['name'] ?? null,
                'is_internal_lending' => (bool) ($meta['is_internal_lending'] ?? false),
                'is_active_in_reports' => (bool) ($meta['is_active_in_reports'] ?? true),
            ];
        })->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function replayAsOf(Carbon $asOf): Collection
    {
        $placeholders = implode(',', array_fill(0, count(self::NERACA_TYPES), '?'));
        $bindings = array_merge(
            [Transaction::STATUS_COMPLETED, $asOf->toDateString()],
            self::NERACA_TYPES,
            [Transaction::STATUS_COMPLETED, $asOf->toDateString()],
            self::NERACA_TYPES,
        );

        $sql = "
            SELECT customer_id, customer_type, balance
            FROM (
                SELECT customer_id, customer_type, balance,
                    ROW_NUMBER() OVER (
                        PARTITION BY customer_id, customer_type
                        ORDER BY date DESC, id DESC
                    ) AS rn
                FROM (
                    SELECT sender_id AS customer_id, sender_type AS customer_type,
                           sender_balance AS balance, date, id
                    FROM transactions
                    WHERE status = ?
                      AND date <= ?
                      AND sender_id IS NOT NULL
                      AND sender_id > 0
                      AND sender_type IN ({$placeholders})
                    UNION ALL
                    SELECT receiver_id, receiver_type, receiver_balance, date, id
                    FROM transactions
                    WHERE status = ?
                      AND date <= ?
                      AND receiver_id IS NOT NULL
                      AND receiver_id > 0
                      AND receiver_type IN ({$placeholders})
                ) sides
            ) ranked
            WHERE rn = 1
        ";

        $raw = collect(DB::select($sql, $bindings));
        $contacts = $this->contactMeta($raw->pluck('customer_id')->map(fn ($id) => (int) $id)->all());
        $bankEntities = $this->bankEntityMap();

        return $raw->map(function (object $row) use ($contacts, $bankEntities) {
            $id = (int) $row->customer_id;
            $type = (int) $row->customer_type;
            $meta = $contacts->get($id);
            $entityId = $this->resolveEntityId($id, $type, $meta, $bankEntities);

            return (object) [
                'customer_id' => $id,
                'customer_type' => $type,
                'balance' => (float) $row->balance,
                'reporting_entity_id' => $entityId,
                'name' => $meta['name'] ?? null,
                'is_internal_lending' => (bool) ($meta['is_internal_lending'] ?? false),
                'is_active_in_reports' => (bool) ($meta['is_active_in_reports'] ?? true),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public function persistSnapshot(Carbon $asOf, Collection $rows): void
    {
        $date = $asOf->toDateString();
        $now = now();

        ReportingBalanceSnapshot::query()->whereDate('as_of_date', $date)->delete();

        foreach ($rows->chunk(200) as $chunk) {
            $payload = $chunk->map(fn (object $row) => [
                'as_of_date' => $date,
                'customer_id' => $row->customer_id,
                'customer_type' => $row->customer_type,
                'reporting_entity_id' => $row->reporting_entity_id,
                'balance' => $row->balance,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($payload !== []) {
                ReportingBalanceSnapshot::query()->insert($payload);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array{name: string|null, is_internal_lending: bool, is_active_in_reports: bool, default_bank_id: int|null}>
     */
    private function contactMeta(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Addrbook::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'is_internal_lending', 'is_active_in_reports', 'default_bank_id'])
            ->mapWithKeys(fn (Addrbook $contact) => [
                (int) $contact->id => [
                    'name' => $contact->name,
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
     * @param  array{name: string|null, is_internal_lending: bool, is_active_in_reports: bool, default_bank_id: int|null}|null  $meta
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
}
