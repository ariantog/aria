<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\DataRetentionRun;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DataRetentionService
{
    /**
     * L10 MySQL partitions are named by their upper-bound year, not the calendar year stored inside.
     * Example: calendar 2014 rows live in p2015 (date >= 2014-01-01 and < 2015-01-01).
     * p2014 is a catch-all for date < 2014-01-01 and cannot drop a single calendar year.
     */
    public const FIRST_DEDICATED_PARTITION_CALENDAR_YEAR = 2014;

    /** @var array<string, int> */
    public const PURGEABLE_ADDRBOOK_TYPES = [
        'customers' => Addrbook::TYPE_CUSTOMER,
        'suppliers' => Addrbook::TYPE_SUPPLIER,
        'resellers' => Addrbook::TYPE_RESELLER,
    ];

    public function retentionYears(): int
    {
        return max(1, (int) config('data_retention.retention_years', 5));
    }

    /** First calendar year kept on the live database. */
    public function liveRetentionStartYear(?Carbon $now = null): int
    {
        $now ??= now();

        return $now->year - $this->retentionYears() + 1;
    }

    /**
     * @return array<int, int>
     */
    public function yearsEligibleForArchive(?Carbon $now = null): array
    {
        $start = $this->liveRetentionStartYear($now);
        $years = [];

        foreach ($this->distinctTransactionYears() as $year) {
            if ($year < $start) {
                $years[] = $year;
            }
        }

        sort($years);

        return $years;
    }

    public function partitionName(int $year): string
    {
        return $this->partitionNameForCalendarYear($year);
    }

    public function partitionNameForCalendarYear(int $year): string
    {
        return 'p'.($year + 1);
    }

    public function calendarYearUsesPartitionDrop(int $year): bool
    {
        return $year >= self::FIRST_DEDICATED_PARTITION_CALENDAR_YEAR;
    }

    public function archiveConfigured(): bool
    {
        try {
            $this->archive()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function archiveDriver(): string
    {
        return (string) config('database.connections.archive.driver');
    }

    public function usesPartitioning(): bool
    {
        return $this->live()->getDriverName() === 'mysql'
            && $this->tableIsPartitioned('transactions');
    }

    public function runForYear(int $year): DataRetentionRun
    {
        return DataRetentionRun::query()->firstOrCreate(
            ['year' => $year],
            ['status' => DataRetentionRun::STATUS_PENDING],
        );
    }

    /**
     * @return array{
     *     year: int,
     *     transactions: int,
     *     details: int,
     *     customers: int,
     *     items: int,
     *     item_groups: int
     * }
     */
    public function previewArchiveYear(int $year): array
    {
        $bounds = $this->yearBounds($year);

        $transactions = (int) $this->live()->table('transactions')
            ->whereBetween('date', $bounds)
            ->count();

        $details = (int) $this->live()->table('transaction_details')
            ->whereBetween('date', $bounds)
            ->count();

        $partyIds = $this->partyIdsForYear($year);
        $itemIds = $this->itemIdsForYear($year);
        $createdCustomerIds = $this->entityIdsCreatedInYear('customers', $year);
        $createdItemIds = $this->entityIdsCreatedInYear('items', $year);

        $customerIds = $partyIds->merge($createdCustomerIds)->unique()->values();
        $allItemIds = $itemIds->merge($createdItemIds)->unique()->values();
        $groupIds = $this->groupIdsForItems($allItemIds);

        return [
            'year' => $year,
            'transactions' => $transactions,
            'details' => $details,
            'customers' => $customerIds->count(),
            'items' => $allItemIds->count(),
            'item_groups' => $groupIds->count(),
        ];
    }

    /**
     * Copy one calendar year from live → archive (idempotent inserts).
     *
     * @return array{transactions: int, details: int, customers: int, items: int}
     */
    public function archiveYear(int $year, bool $dryRun = false): array
    {
        $this->assertArchiveReady();
        $this->assertYearEligible($year);

        $run = $this->runForYear($year);

        if ($run->isCleaned()) {
            throw new \RuntimeException("Year {$year} was already cleaned from the live database.");
        }

        if ($dryRun) {
            $preview = $this->previewArchiveYear($year);

            return [
                'transactions' => $preview['transactions'],
                'details' => $preview['details'],
                'customers' => $preview['customers'],
                'items' => $preview['items'],
            ];
        }

        $run->update([
            'status' => DataRetentionRun::STATUS_COPYING,
            'archive_started_at' => now(),
            'last_error' => null,
        ]);

        try {
            $bounds = $this->yearBounds($year);
            $batch = config('data_retention.copy_batch_size', 500);

            $transactionsCopied = $this->copyTableRows(
                'transactions',
                fn ($query) => $query->whereBetween('date', $bounds),
                $batch,
            );

            $detailsCopied = $this->copyTableRows(
                'transaction_details',
                fn ($query) => $query->whereBetween('date', $bounds),
                $batch,
            );

            $partyIds = $this->partyIdsForYear($year);
            $itemIds = $this->itemIdsForYear($year);
            $customerIds = $partyIds
                ->merge($this->entityIdsCreatedInYear('customers', $year))
                ->unique()
                ->values();
            $allItemIds = $itemIds
                ->merge($this->entityIdsCreatedInYear('items', $year))
                ->unique()
                ->values();
            $groupIds = $this->groupIdsForItems($allItemIds);

            $groupsCopied = $this->copyIds('item_group', $groupIds, $batch);
            $customersCopied = $this->copyIds('customers', $customerIds, $batch);
            $itemsCopied = $this->copyIds('items', $allItemIds, $batch);

            $run->update([
                'status' => DataRetentionRun::STATUS_ARCHIVED,
                'transactions_copied' => $transactionsCopied,
                'details_copied' => $detailsCopied,
                'customers_copied' => $customersCopied,
                'items_copied' => $itemsCopied,
                'archive_finished_at' => now(),
                'last_error' => null,
            ]);

            return [
                'transactions' => $transactionsCopied,
                'details' => $detailsCopied,
                'customers' => $customersCopied,
                'items' => $itemsCopied,
            ];
        } catch (Throwable $e) {
            $run->update([
                'status' => DataRetentionRun::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{
     *     year: int,
     *     transactions: int,
     *     details: int,
     *     monthly_stats: int,
     *     monthly_accounts: int,
     *     daily_customers: int,
     *     orphan_items: int,
     *     uses_partition_drop: bool
     * }
     */
    public function previewLiveCleanup(int $year): array
    {
        $bounds = $this->yearBounds($year);

        $transactions = (int) $this->live()->table('transactions')
            ->whereBetween('date', $bounds)
            ->count();

        $details = (int) $this->live()->table('transaction_details')
            ->whereBetween('date', $bounds)
            ->count();

        return [
            'year' => $year,
            'transactions' => $transactions,
            'details' => $details,
            'monthly_stats' => $this->countMonthlyStatsForYear($year),
            'monthly_accounts' => $this->countMonthlyAccountsForYear($year),
            'daily_customers' => $this->countCustomerDailyForYear($year),
            'orphan_items' => $this->countOrphanItems($this->liveRetentionStartYear()),
            'orphan_item_groups' => $this->countOrphanItemGroups(),
            'orphan_customers' => $this->countOrphanAddrbooks(Addrbook::TYPE_CUSTOMER),
            'orphan_suppliers' => $this->countOrphanAddrbooks(Addrbook::TYPE_SUPPLIER),
            'orphan_resellers' => $this->countOrphanAddrbooks(Addrbook::TYPE_RESELLER),
            'partition_name' => $this->calendarYearUsesPartitionDrop($year)
                ? $this->partitionNameForCalendarYear($year)
                : null,
            'uses_partition_drop' => $this->usesPartitioning()
                && $this->calendarYearUsesPartitionDrop($year)
                && $this->partitionExists('transactions', $year),
        ];
    }

    /**
     * @return array{
     *     cutoff_year: int,
     *     items: int,
     *     item_groups: int,
     *     customers: int,
     *     suppliers: int,
     *     resellers: int,
     *     items_with_stock: int
     * }
     */
    public function previewOrphanPurges(?int $cutoffYear = null): array
    {
        $cutoffYear ??= $this->liveRetentionStartYear();

        return [
            'cutoff_year' => $cutoffYear,
            'items' => $this->countOrphanItems($cutoffYear),
            'item_groups' => $this->countOrphanItemGroups(),
            'customers' => $this->countOrphanAddrbooks(Addrbook::TYPE_CUSTOMER, $cutoffYear),
            'suppliers' => $this->countOrphanAddrbooks(Addrbook::TYPE_SUPPLIER, $cutoffYear),
            'resellers' => $this->countOrphanAddrbooks(Addrbook::TYPE_RESELLER, $cutoffYear),
            'items_with_stock' => $this->countOrphanItems($cutoffYear, ignoreWarehouseStock: true),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function previewSelectableItemPurge(
        int $cutoffYear,
        ?int $itemType = null,
        bool $ignoreWarehouseStock = true,
        int $limit = 50,
    ): array {
        $query = $this->orphanItemIdsQuery($cutoffYear, $ignoreWarehouseStock);

        if ($itemType !== null) {
            $query->where('items.type', $itemType);
        }

        $total = (int) (clone $query)->count('items.id');

        $rows = $query
            ->select([
                'items.id',
                'items.code',
                'items.name',
                'items.type',
                'items.created_at',
                'items.deleted_at',
            ])
            ->selectRaw('COALESCE((SELECT SUM(warehouse_item.quantity) FROM warehouse_item WHERE warehouse_item.item_id = items.id), 0) as warehouse_qty')
            ->orderBy('items.id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => (int) $row->type,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
                'warehouse_qty' => (float) $row->warehouse_qty,
            ])
            ->all();

        return ['items' => $rows, 'total' => $total];
    }

    /**
     * Remove one archived year from the live database.
     *
     * @return array{transactions: int, details: int, items_purged: int}
     */
    public function cleanupLiveYear(int $year, bool $dryRun = false): array
    {
        $this->assertYearEligible($year);

        $run = $this->runForYear($year);

        if (! $run->isArchived()) {
            throw new \RuntimeException("Year {$year} must be copied to the archive database before live cleanup.");
        }

        if ($run->isCleaned()) {
            if ($this->countTransactionsForYear($year) === 0) {
                throw new \RuntimeException("Year {$year} was already cleaned from the live database.");
            }

            // Prior cleanup marked success but rows remain (e.g. wrong partition dropped) — allow retry.
            $run->update([
                'status' => DataRetentionRun::STATUS_ARCHIVED,
                'cleanup_finished_at' => null,
                'last_error' => null,
            ]);
        }

        $preview = $this->previewLiveCleanup($year);

        if ($dryRun) {
            return [
                'transactions' => $preview['transactions'],
                'details' => $preview['details'],
                'items_purged' => $preview['orphan_items'],
                'item_groups_purged' => $preview['orphan_item_groups'],
            ];
        }

        $run->update([
            'status' => DataRetentionRun::STATUS_CLEANING,
            'cleanup_started_at' => now(),
            'last_error' => null,
        ]);

        try {
            if ($preview['uses_partition_drop']) {
                $this->dropYearPartition('transactions', $year);
                $this->dropYearPartition('transaction_details', $year);
            } else {
                $bounds = $this->yearBounds($year);
                $this->live()->table('transaction_details')->whereBetween('date', $bounds)->delete();
                $this->live()->table('transactions')->whereBetween('date', $bounds)->delete();
            }

            $this->purgeAggregateRowsForYear($year);

            $this->assertYearRemovedFromLive($year);

            $itemPurge = $this->purgeOrphanItemsFromLive(false);

            $run->update([
                'status' => DataRetentionRun::STATUS_CLEANED,
                'items_purged' => $itemPurge['items'],
                'cleanup_finished_at' => now(),
                'last_error' => null,
            ]);

            return [
                'transactions' => $preview['transactions'],
                'details' => $preview['details'],
                'items_purged' => $itemPurge['items'],
                'item_groups_purged' => $itemPurge['groups'],
            ];
        } catch (Throwable $e) {
            $run->update([
                'status' => DataRetentionRun::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{items: int, groups: int}
     */
    public function purgeOrphanItemsFromLive(
        bool $dryRun = false,
        bool $ignoreWarehouseStock = false,
        ?int $cutoffYear = null,
        ?int $itemType = null,
    ): array {
        $cutoffYear ??= $this->liveRetentionStartYear();
        $batch = config('data_retention.item_purge_batch_size', 500);
        $purged = 0;

        while (true) {
            $query = $this->orphanItemIdsQuery($cutoffYear, $ignoreWarehouseStock)
                ->orderBy('items.id')
                ->limit($batch);

            if ($itemType !== null) {
                $query->where('items.type', $itemType);
            }

            $ids = $query->pluck('items.id');

            if ($ids->isEmpty()) {
                break;
            }

            if ($dryRun) {
                return [
                    'items' => $purged + $ids->count(),
                    'groups' => $this->countOrphanItemGroups(),
                ];
            }

            foreach ($ids as $id) {
                DB::transaction(fn () => $this->hardDeleteItem((int) $id));
                $purged++;
            }
        }

        return [
            'items' => $purged,
            'groups' => $this->purgeOrphanItemGroupsFromLive(false),
        ];
    }

    public function purgeOrphanItemGroupsFromLive(bool $dryRun = false): int
    {
        $batch = config('data_retention.item_purge_batch_size', 500);
        $purged = 0;

        while (true) {
            $ids = $this->orphanItemGroupIdsQuery()
                ->orderBy('item_group.id')
                ->limit($batch)
                ->pluck('item_group.id');

            if ($ids->isEmpty()) {
                break;
            }

            if ($dryRun) {
                return $purged + $ids->count();
            }

            DB::table('item_group')->whereIn('id', $ids->all())->delete();
            $purged += $ids->count();
        }

        return $purged;
    }

    public function purgeOrphanAddrbooksFromLive(int $type, bool $dryRun = false, ?int $cutoffYear = null): int
    {
        if (! in_array($type, self::PURGEABLE_ADDRBOOK_TYPES, true)) {
            throw new \InvalidArgumentException('Addrbook type is not eligible for orphan purge.');
        }

        $cutoffYear ??= $this->liveRetentionStartYear();
        $batch = config('data_retention.item_purge_batch_size', 500);
        $purged = 0;

        while (true) {
            $ids = $this->orphanAddrbookIdsQuery($type, $cutoffYear)
                ->orderBy('customers.id')
                ->limit($batch)
                ->pluck('customers.id');

            if ($ids->isEmpty()) {
                break;
            }

            if ($dryRun) {
                return $purged + $ids->count();
            }

            foreach ($ids as $id) {
                DB::transaction(fn () => $this->hardDeleteAddrbook((int) $id));
                $purged++;
            }
        }

        return $purged;
    }

    public function confirmTokenForAddrbookType(int $type): string
    {
        return match ($type) {
            Addrbook::TYPE_CUSTOMER => 'PURGE-ORPHAN-CUSTOMERS',
            Addrbook::TYPE_SUPPLIER => 'PURGE-ORPHAN-SUPPLIERS',
            Addrbook::TYPE_RESELLER => 'PURGE-ORPHAN-RESELLERS',
            default => throw new \InvalidArgumentException('Unsupported addrbook purge type.'),
        };
    }

    /**
     * @return array<int, int>
     */
    public function archiveTransactionYears(): array
    {
        if (! $this->archiveConfigured()) {
            return [];
        }

        try {
            if (! Schema::connection('archive')->hasTable('transactions')) {
                return [];
            }

            return $this->distinctYearsFromTable($this->archive(), 'transactions', 'date');
        } catch (Throwable) {
            return [];
        }
    }

    protected function live(): Connection
    {
        return DB::connection();
    }

    protected function archive(): Connection
    {
        return DB::connection('archive');
    }

    protected function assertArchiveReady(): void
    {
        if (! $this->archiveConfigured()) {
            throw new \RuntimeException('Archive database is not configured or not reachable. Set ARCHIVE_DB_* in .env.');
        }

        if (! Schema::connection('archive')->hasTable('transactions')) {
            throw new \RuntimeException('Archive database is missing the transactions table. Import a schema/data dump first.');
        }
    }

    protected function assertYearEligible(int $year): void
    {
        if ($year >= $this->liveRetentionStartYear()) {
            throw new \RuntimeException("Year {$year} is inside the live retention window.");
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function yearBounds(int $year): array
    {
        return [
            sprintf('%04d-01-01', $year),
            sprintf('%04d-12-31', $year),
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function distinctTransactionYears(): array
    {
        if (! Schema::hasTable('transactions')) {
            return [];
        }

        return $this->distinctYearsFromTable($this->live(), 'transactions', 'date');
    }

    /**
     * @return array<int, int>
     */
    protected function distinctYearsFromTable(Connection $connection, string $table, string $column): array
    {
        if ($connection->getDriverName() === 'sqlite') {
            return $connection->table($table)
                ->selectRaw("DISTINCT CAST(strftime('%Y', {$column}) AS INTEGER) as year")
                ->orderBy('year')
                ->pluck('year')
                ->map(fn ($year) => (int) $year)
                ->all();
        }

        return $connection->table($table)
            ->selectRaw("DISTINCT YEAR({$column}) as year")
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    protected function partyIdsForYear(int $year): Collection
    {
        $bounds = $this->yearBounds($year);

        $fromTransactions = $this->live()->table('transactions')
            ->whereBetween('date', $bounds)
            ->select(['sender_id', 'receiver_id'])
            ->get()
            ->flatMap(fn ($row) => [(int) $row->sender_id, (int) $row->receiver_id])
            ->filter(fn (int $id) => $id > 0);

        $fromDetails = $this->live()->table('transaction_details')
            ->whereBetween('date', $bounds)
            ->select(['sender_id', 'receiver_id'])
            ->get()
            ->flatMap(fn ($row) => [(int) $row->sender_id, (int) $row->receiver_id])
            ->filter(fn (int $id) => $id > 0);

        return $fromTransactions->merge($fromDetails)->unique()->values();
    }

    protected function itemIdsForYear(int $year): Collection
    {
        $bounds = $this->yearBounds($year);

        return $this->live()->table('transaction_details')
            ->whereBetween('date', $bounds)
            ->where('item_id', '>', 0)
            ->distinct()
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function entityIdsCreatedInYear(string $table, int $year): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_at')) {
            return collect();
        }

        return $this->live()->table($table)
            ->whereBetween('created_at', $this->yearBounds($year))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function groupIdsForItems(Collection $itemIds): Collection
    {
        if ($itemIds->isEmpty() || ! Schema::hasTable('items')) {
            return collect();
        }

        return $this->live()->table('items')
            ->whereIn('id', $itemIds->all())
            ->where('group_id', '>', 0)
            ->distinct()
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function copyTableRows(string $table, callable $scope, int $batch): int
    {
        $copied = 0;
        $query = $this->live()->table($table);
        $scope($query);

        $query->orderBy('id')->chunk($batch, function ($rows) use ($table, &$copied) {
            $payload = $this->filterRowsForArchiveTable($table, collect($rows)->map(fn ($row) => (array) $row)->all());
            if ($payload === []) {
                return;
            }

            $this->archive()->table($table)->insertOrIgnore($payload);
            $copied += count($payload);
        });

        return $copied;
    }

    protected function copyIds(string $table, Collection $ids, int $batch): int
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table)) {
            return 0;
        }

        $copied = 0;

        foreach ($ids->chunk($batch) as $chunk) {
            $rows = $this->live()->table($table)->whereIn('id', $chunk->all())->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $payload = $this->filterRowsForArchiveTable($table, $rows->map(fn ($row) => (array) $row)->all());
            if ($payload === []) {
                continue;
            }

            $this->archive()->table($table)->insertOrIgnore($payload);
            $copied += count($payload);
        }

        return $copied;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filterRowsForArchiveTable(string $table, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $columns = array_flip(Schema::connection('archive')->getColumnListing($table));

        return array_values(array_map(
            fn (array $row) => array_intersect_key($row, $columns),
            $rows,
        ));
    }

    protected function tableIsPartitioned(string $table): bool
    {
        if ($this->live()->getDriverName() !== 'mysql') {
            return false;
        }

        $database = $this->live()->getDatabaseName();
        $row = $this->live()->selectOne(
            'SELECT COUNT(*) AS partitions FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            [$database, $table],
        );

        return ((int) ($row->partitions ?? 0)) > 0;
    }

    protected function countTransactionsForYear(int $year): int
    {
        return (int) $this->live()->table('transactions')
            ->whereBetween('date', $this->yearBounds($year))
            ->count();
    }

    protected function assertYearRemovedFromLive(int $year): void
    {
        $remaining = $this->countTransactionsForYear($year);

        if ($remaining > 0) {
            throw new \RuntimeException(
                "Cleanup incomplete: {$remaining} transaction(s) still exist for calendar year {$year}."
            );
        }
    }

    protected function partitionExists(string $table, int $year): bool
    {
        if ($this->live()->getDriverName() !== 'mysql') {
            return false;
        }

        $database = $this->live()->getDatabaseName();
        $partition = $this->partitionNameForCalendarYear($year);
        $row = $this->live()->selectOne(
            'SELECT PARTITION_NAME FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME = ? LIMIT 1',
            [$database, $table, $partition],
        );

        return $row !== null;
    }

    protected function dropYearPartition(string $table, int $year): void
    {
        if (! $this->calendarYearUsesPartitionDrop($year)) {
            throw new \RuntimeException(
                "Calendar year {$year} shares partition p2014 with all pre-2014 data; use row delete instead."
            );
        }

        $partition = $this->partitionNameForCalendarYear($year);
        $this->live()->statement("ALTER TABLE `{$table}` DROP PARTITION `{$partition}`");
    }

    protected function purgeAggregateRowsForYear(int $year): void
    {
        if (Schema::hasTable('warehouse_item_monthly_stats')) {
            $this->live()->table('warehouse_item_monthly_stats')
                ->where('year', $year)
                ->delete();
        }

        if (Schema::hasTable('monthly_account_summaries')) {
            $this->live()->table('monthly_account_summaries')
                ->where('year', $year)
                ->delete();
        }

        if (Schema::hasTable('monthly_category_summaries')) {
            $this->live()->table('monthly_category_summaries')
                ->where('year', $year)
                ->delete();
        }

        if (Schema::hasTable('monthly_item_sales')) {
            $this->live()->table('monthly_item_sales')
                ->where('year', $year)
                ->delete();
        }

        if (Schema::hasTable('customer_class')) {
            [$from, $to] = $this->yearBounds($year);
            $this->live()->table('customer_class')
                ->whereBetween('date', [$from, $to])
                ->delete();
        }
    }

    protected function countMonthlyStatsForYear(int $year): int
    {
        if (! Schema::hasTable('warehouse_item_monthly_stats')) {
            return 0;
        }

        return (int) $this->live()->table('warehouse_item_monthly_stats')->where('year', $year)->count();
    }

    protected function countMonthlyAccountsForYear(int $year): int
    {
        if (! Schema::hasTable('monthly_account_summaries')) {
            return 0;
        }

        return (int) $this->live()->table('monthly_account_summaries')->where('year', $year)->count();
    }

    protected function countCustomerDailyForYear(int $year): int
    {
        if (! Schema::hasTable('customer_class')) {
            return 0;
        }

        return (int) $this->live()->table('customer_class')
            ->whereBetween('date', $this->yearBounds($year))
            ->count();
    }

    public function countOrphanItems(int $cutoffYear, bool $ignoreWarehouseStock = false): int
    {
        return (int) $this->orphanItemIdsQuery($cutoffYear, $ignoreWarehouseStock)->count('items.id');
    }

    public function countOrphanItemGroups(): int
    {
        return (int) $this->orphanItemGroupIdsQuery()->count('item_group.id');
    }

    public function countOrphanAddrbooks(int $type, ?int $cutoffYear = null): int
    {
        $cutoffYear ??= $this->liveRetentionStartYear();

        return (int) $this->orphanAddrbookIdsQuery($type, $cutoffYear)->count('customers.id');
    }

    protected function orphanItemIdsQuery(int $cutoffYear, bool $ignoreWarehouseStock = false): \Illuminate\Database\Query\Builder
    {
        $cutoffDate = sprintf('%04d-01-01', $cutoffYear);

        $query = $this->live()->table('items')
            ->where('items.created_at', '<', $cutoffDate)
            ->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('transaction_details')
                    ->whereColumn('transaction_details.item_id', 'items.id');
            });

        if (! $ignoreWarehouseStock) {
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('warehouse_item')
                    ->whereColumn('warehouse_item.item_id', 'items.id')
                    ->where('warehouse_item.quantity', '>', 0);
            });
        }

        return $query;
    }

    protected function orphanItemGroupIdsQuery(): \Illuminate\Database\Query\Builder
    {
        return $this->live()->table('item_group')
            ->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('items')
                    ->whereColumn('items.group_id', 'item_group.id')
                    ->where('items.group_id', '>', 0);
            });
    }

    protected function orphanAddrbookIdsQuery(int $type, int $cutoffYear): \Illuminate\Database\Query\Builder
    {
        $cutoffDate = sprintf('%04d-01-01', $cutoffYear);

        $query = $this->live()->table('customers')
            ->where('customers.type', $type)
            ->where('customers.created_at', '<', $cutoffDate);

        foreach (['transactions', 'transaction_details', 'deleted', 'deleted_details'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query->whereNotExists(function ($subquery) use ($table) {
                $subquery->select(DB::raw(1))
                    ->from($table)
                    ->where(function ($partyQuery) use ($table) {
                        $partyQuery->whereColumn("{$table}.sender_id", 'customers.id')
                            ->orWhereColumn("{$table}.receiver_id", 'customers.id');
                    });
            });
        }

        return $query;
    }

    protected function hardDeleteItem(int $id): void
    {
        if (Schema::hasTable('item_tag')) {
            DB::table('item_tag')->where('item_id', $id)->delete();
        }

        if (Schema::hasTable('item_identity_conversion_results')) {
            DB::table('item_identity_conversion_results')->where('item_id', $id)->delete();
        }

        if (Schema::hasTable('warehouse_item')) {
            DB::table('warehouse_item')->where('item_id', $id)->delete();
        }

        DB::table('items')->where('id', $id)->delete();
    }

    protected function hardDeleteAddrbook(int $id): void
    {
        if (Schema::hasTable('customerstat')) {
            DB::table('customerstat')->where('customer_id', $id)->delete();
        }

        if (Schema::hasTable('customer_class')) {
            DB::table('customer_class')->where('customer_id', $id)->delete();
        }

        if (Schema::hasTable('monthly_account_summaries')) {
            DB::table('monthly_account_summaries')->where('customer_id', $id)->delete();
        }

        if (Schema::hasTable('location_customer')) {
            DB::table('location_customer')->where('customer_id', $id)->delete();
        }

        foreach (['reporting_channel_banks', 'reporting_warehouse_fulfillment', 'reporting_ledger_roles'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('customer_id', $id)->delete();
            }
        }

        if (Schema::hasTable('ledger_merge_maps')) {
            DB::table('ledger_merge_maps')
                ->where(function ($query) use ($id) {
                    $query->where('old_customer_id', $id)
                        ->orWhere('new_customer_id', $id);
                })
                ->delete();
        }

        DB::table('customers')->where('id', $id)->delete();
    }
}
