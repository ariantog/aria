<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\Items\ItemDimensionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds warehouse monthly stats one period at a time.
 *
 * Working month by month keeps peak memory proportional to the busiest single
 * month instead of the whole transaction history, and lets MySQL prune
 * partitions on the date-partitioned transaction_details table.
 */
class WarehouseItemStatsRebuilder
{
    public const DEFAULT_CHUNK = 500;

    /**
     * Legacy MySQL rows can carry '0000-00-00'. Without this floor a period scan
     * would start at year zero and run for thousands of iterations.
     */
    public const EARLIEST_SUPPORTED_DATE = '1990-01-01';

    /**
     * Item dimensions are reused across months, but the cache is dropped once it
     * grows past this many items so a long history cannot exhaust memory.
     */
    private const DIMENSION_CACHE_LIMIT = 20000;

    /** @var array<int, array<string, mixed>> */
    private array $dimensionCache = [];

    public function __construct(private readonly ItemDimensionResolver $dimensions) {}

    public static function periodKey(CarbonImmutable $date): int
    {
        return $date->year * 12 + $date->month;
    }

    public static function periodFromKey(int $key): CarbonImmutable
    {
        $year = intdiv($key - 1, 12);
        $month = $key - ($year * 12);

        return CarbonImmutable::create($year, $month, 1)->startOfMonth();
    }

    /**
     * Earliest and latest month that hold sell or return details.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function periodBounds(): ?array
    {
        $bounds = DB::table('transaction_details')
            ->whereIn('transaction_type', [Transaction::TYPE_SELL, Transaction::TYPE_RETURN])
            ->where('date', '>=', self::EARLIEST_SUPPORTED_DATE)
            ->selectRaw('MIN(date) as min_date, MAX(date) as max_date')
            ->first();

        if (! $bounds || ! $bounds->min_date || ! $bounds->max_date) {
            return null;
        }

        return [
            CarbonImmutable::parse($bounds->min_date)->startOfMonth(),
            CarbonImmutable::parse($bounds->max_date)->startOfMonth(),
        ];
    }

    /**
     * Rebuild a single month in isolation. Returns the number of stat rows written.
     */
    public function rebuildMonth(CarbonImmutable $period, int $chunkSize = self::DEFAULT_CHUNK): int
    {
        $periodStart = $period->startOfMonth();
        $periodEnd = $periodStart->addMonth();
        $chunkSize = max(1, $chunkSize);

        $stats = [];

        $this->accumulate(
            $stats,
            $this->aggregateMonth('sender_id', Transaction::TYPE_SELL, $periodStart, $periodEnd),
            'sold_qty',
            'sold_value',
        );

        $this->accumulate(
            $stats,
            $this->aggregateMonth('receiver_id', Transaction::TYPE_RETURN, $periodStart, $periodEnd),
            'returned_qty',
            'returned_value',
        );

        // Always clear the month first so re-runs are idempotent and a partial
        // rebuild leaves untouched periods alone.
        WarehouseItemMonthlyStat::query()
            ->where('year', $periodStart->year)
            ->where('month', $periodStart->month)
            ->delete();

        if ($stats === []) {
            return 0;
        }

        $resolved = $this->dimensionsFor(array_column($stats, 'item_id'));

        $now = now();
        $buffer = [];
        $written = 0;

        foreach ($stats as $stat) {
            $dims = $resolved[$stat['item_id']] ?? null;
            if (! $dims) {
                continue;
            }

            $buffer[] = array_merge($dims, [
                'warehouse_id' => $stat['warehouse_id'],
                'item_id' => $stat['item_id'],
                'month' => $periodStart->month,
                'year' => $periodStart->year,
                'sold_qty' => $stat['sold_qty'],
                'sold_value' => $stat['sold_value'],
                'returned_qty' => $stat['returned_qty'],
                'returned_value' => $stat['returned_value'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (count($buffer) >= $chunkSize) {
                DB::table('warehouse_item_monthly_stats')->insert($buffer);
                $written += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('warehouse_item_monthly_stats')->insert($buffer);
            $written += count($buffer);
        }

        unset($stats, $resolved, $buffer);

        return $written;
    }

    /**
     * Drop stats for periods outside the given range, so a full rebuild cannot
     * leave orphaned rows behind (e.g. after transactions were deleted).
     */
    public function purgeOutsideRange(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): void
    {
        WarehouseItemMonthlyStat::query()
            ->whereRaw('(year * 12 + month) < ?', [self::periodKey($rangeStart)])
            ->orWhereRaw('(year * 12 + month) > ?', [self::periodKey($rangeEnd)])
            ->delete();
    }

    /**
     * Aggregate one month straight from the partitioned transaction_details table.
     * The date range prunes partitions, and month/year are known constants here so
     * no MONTH()/YEAR() call is needed in SQL.
     *
     * @return Collection<int, object>
     */
    private function aggregateMonth(
        string $warehouseColumn,
        int $transactionType,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): Collection {
        $addrbookTable = (new Addrbook)->getTable();

        return DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            // Legacy rows can hold 0 or orphaned warehouse ids; join to keep only
            // real warehouses so the FK on warehouse_item_monthly_stats holds.
            ->join($addrbookTable.' as wh', 'wh.id', '=', 'transaction_details.'.$warehouseColumn)
            ->where('transaction_details.transaction_type', $transactionType)
            ->where('transaction_details.date', '>=', $periodStart->toDateString())
            ->where('transaction_details.date', '<', $periodEnd->toDateString())
            ->selectRaw(
                'transaction_details.'.$warehouseColumn.' as warehouse_id,'
                .' transaction_details.item_id,'
                .' SUM(ABS(transaction_details.quantity)) as qty,'
                .' SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value'
            )
            ->groupBy('transaction_details.'.$warehouseColumn, 'transaction_details.item_id')
            ->get();
    }

    /**
     * @param  array<string, array<string, mixed>>  $stats
     * @param  Collection<int, object>  $rows
     */
    private function accumulate(array &$stats, Collection $rows, string $qtyColumn, string $valueColumn): void
    {
        foreach ($rows as $row) {
            $qty = (float) $row->qty;
            $value = (float) $row->value;

            if ($qty <= 0 && $value <= 0) {
                continue;
            }

            $key = $row->warehouse_id.'|'.$row->item_id;

            if (! isset($stats[$key])) {
                $stats[$key] = [
                    'warehouse_id' => (int) $row->warehouse_id,
                    'item_id' => (int) $row->item_id,
                    'sold_qty' => 0.0,
                    'sold_value' => 0.0,
                    'returned_qty' => 0.0,
                    'returned_value' => 0.0,
                ];
            }

            $stats[$key][$qtyColumn] += $qty;
            $stats[$key][$valueColumn] += $value;
        }
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, array<string, mixed>>
     */
    private function dimensionsFor(array $itemIds): array
    {
        $itemIds = array_values(array_unique($itemIds));
        $missing = array_values(array_diff($itemIds, array_keys($this->dimensionCache)));

        if ($missing !== []) {
            if (count($this->dimensionCache) > self::DIMENSION_CACHE_LIMIT) {
                $this->dimensionCache = [];
            }

            $this->dimensionCache += $this->dimensions->resolveMany($missing);
        }

        $resolved = [];
        foreach ($itemIds as $itemId) {
            if (isset($this->dimensionCache[$itemId])) {
                $resolved[$itemId] = $this->dimensionCache[$itemId];
            }
        }

        return $resolved;
    }
}
