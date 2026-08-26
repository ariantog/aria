<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\Items\ItemDimensionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecalculateWarehouseItemStats extends Command
{
    protected $signature = 'app:recalculate-warehouse-item-stats
                            {--months= : Only rebuild the last N months instead of the full history}
                            {--since= : Only rebuild from this date onwards (Y-m-d)}
                            {--chunk=500 : Rows per insert statement}';

    protected $description = 'Rebuild per-warehouse per-SKU monthly sell/return statistics';

    /**
     * Item dimensions are reused across months, but the cache is dropped once it grows
     * past this many items so a long history cannot exhaust memory.
     */
    private const DIMENSION_CACHE_LIMIT = 20000;

    private const EARLIEST_SUPPORTED_DATE = '1990-01-01';

    /** @var array<int, array<string, mixed>> */
    private array $dimensionCache = [];

    public function handle(ItemDimensionResolver $dimensions): int
    {
        DB::connection()->disableQueryLog();

        $range = $this->resolvePeriodRange();

        if ($range === null) {
            $this->info('No sell or return transaction details found; nothing to recalculate.');

            return self::SUCCESS;
        }

        [$rangeStart, $rangeEnd, $isFullRebuild] = $range;

        $monthCount = ($rangeEnd->year - $rangeStart->year) * 12 + ($rangeEnd->month - $rangeStart->month) + 1;

        $this->info(sprintf(
            '%s %s → %s (%d month(s)).',
            $isFullRebuild ? 'Full rebuild' : 'Partial rebuild',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $monthCount,
        ));

        if ($isFullRebuild) {
            $this->purgeOutsideRange($rangeStart, $rangeEnd);
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $started = microtime(true);
        $written = 0;

        $bar = $this->output->createProgressBar($monthCount);
        $bar->start();

        for ($period = $rangeStart; $period->lessThanOrEqualTo($rangeEnd); $period = $period->addMonth()) {
            $written += $this->rebuildMonth($dimensions, $period, $chunkSize);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf('Done in %.1fs. %d monthly stat row(s) written.', microtime(true) - $started, $written));

        return self::SUCCESS;
    }

    /**
     * Rebuild a single month in isolation so peak memory stays proportional to one
     * month of activity rather than the whole transaction history.
     */
    private function rebuildMonth(ItemDimensionResolver $dimensions, CarbonImmutable $period, int $chunkSize): int
    {
        $periodStart = $period->startOfMonth();
        $periodEnd = $period->addMonth()->startOfMonth();

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

        // Always clear the month first so re-runs are idempotent and a partial rebuild
        // leaves untouched periods alone.
        WarehouseItemMonthlyStat::query()
            ->where('year', $periodStart->year)
            ->where('month', $periodStart->month)
            ->delete();

        if ($stats === []) {
            return 0;
        }

        $resolved = $this->dimensionsFor($dimensions, array_column($stats, 'item_id'));

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
     * Aggregate one month of transaction details straight from the partitioned table.
     * The date range lets MySQL prune partitions, and month/year are known constants
     * here so no MONTH()/YEAR() call is needed in SQL.
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
    private function dimensionsFor(ItemDimensionResolver $dimensions, array $itemIds): array
    {
        $itemIds = array_values(array_unique($itemIds));
        $missing = array_values(array_diff($itemIds, array_keys($this->dimensionCache)));

        if ($missing !== []) {
            if (count($this->dimensionCache) > self::DIMENSION_CACHE_LIMIT) {
                $this->dimensionCache = [];
            }

            $this->dimensionCache += $dimensions->resolveMany($missing);
        }

        $resolved = [];
        foreach ($itemIds as $itemId) {
            if (isset($this->dimensionCache[$itemId])) {
                $resolved[$itemId] = $this->dimensionCache[$itemId];
            }
        }

        return $resolved;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}|null
     */
    private function resolvePeriodRange(): ?array
    {
        $bounds = DB::table('transaction_details')
            ->whereIn('transaction_type', [Transaction::TYPE_SELL, Transaction::TYPE_RETURN])
            // Legacy MySQL rows can carry '0000-00-00'; without this floor the month
            // loop would start at year zero and run for thousands of iterations.
            ->where('date', '>=', self::EARLIEST_SUPPORTED_DATE)
            ->selectRaw('MIN(date) as min_date, MAX(date) as max_date')
            ->first();

        if (! $bounds || ! $bounds->min_date || ! $bounds->max_date) {
            return null;
        }

        $earliest = CarbonImmutable::parse($bounds->min_date)->startOfMonth();
        $rangeEnd = CarbonImmutable::parse($bounds->max_date)->startOfMonth();

        $rangeStart = $earliest;
        $isFullRebuild = true;

        if ($since = $this->option('since')) {
            $rangeStart = CarbonImmutable::parse($since)->startOfMonth();
            $isFullRebuild = false;
        } elseif ($months = $this->option('months')) {
            $rangeStart = CarbonImmutable::now()->startOfMonth()->subMonths(max(0, (int) $months - 1));
            $isFullRebuild = false;
        }

        if ($rangeStart->lessThan($earliest)) {
            $rangeStart = $earliest;
        }

        if ($rangeStart->greaterThan($rangeEnd)) {
            return null;
        }

        return [$rangeStart, $rangeEnd, $isFullRebuild];
    }

    /**
     * Drop stats for periods the rebuild will not visit, so a full run cannot leave
     * orphaned rows behind (e.g. after transactions were deleted).
     */
    private function purgeOutsideRange(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): void
    {
        $startKey = $rangeStart->year * 12 + $rangeStart->month;
        $endKey = $rangeEnd->year * 12 + $rangeEnd->month;

        WarehouseItemMonthlyStat::query()
            ->whereRaw('(year * 12 + month) < ?', [$startKey])
            ->orWhereRaw('(year * 12 + month) > ?', [$endKey])
            ->delete();
    }
}
