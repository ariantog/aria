<?php

namespace App\Services;

use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemInsightSyncService
{
    public const TOP_LIMIT = 50;

    /** Minimum net units sold in the month to qualify as a loss leader candidate. */
    public const LOSS_LEADER_MIN_QTY = 1.0;

    /**
     * @return array{year: int, month: int, rows: int, calculated_at: string, months_included?: list<int>}
     */
    public function recalculateMonth(int $year, int $month, ?int $userId = null): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Year is out of range.');
        }

        $aggregates = $this->aggregateCompanyMonths($year, [$month]);
        $daysInPeriod = Carbon::create($year, $month, 1)->daysInMonth;

        return $this->storeRankings(
            $year,
            $month,
            $aggregates,
            $daysInPeriod,
            $userId,
            [$month],
        );
    }

    /**
     * Year rollup uses calculated insight months in that year when any exist; otherwise
     * every month that has warehouse_item_monthly_stats rows for the year.
     *
     * @return array{year: int, month: int, rows: int, calculated_at: string, months_included: list<int>}
     */
    public function recalculateYear(int $year, ?int $userId = null): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Year is out of range.');
        }

        $monthsIncluded = $this->resolveYearSourceMonths($year);
        if ($monthsIncluded === []) {
            return $this->storeRankings($year, ItemInsightMonth::MONTH_YEARLY, [], 0, $userId, []);
        }

        $aggregates = $this->aggregateCompanyMonths($year, $monthsIncluded);
        $daysInPeriod = $this->daysInMonths($year, $monthsIncluded);

        $result = $this->storeRankings(
            $year,
            ItemInsightMonth::MONTH_YEARLY,
            $aggregates,
            $daysInPeriod,
            $userId,
            $monthsIncluded,
        );
        $result['months_included'] = $monthsIncluded;

        return $result;
    }

    /**
     * Prefer months already calculated for item insights; fall back to warehouse stats months.
     *
     * @return list<int>
     */
    public function resolveYearSourceMonths(int $year): array
    {
        $fromInsights = ItemInsightMonth::query()
            ->where('year', $year)
            ->whereBetween('month', [1, 12])
            ->orderBy('month')
            ->pluck('month')
            ->map(fn ($m) => (int) $m)
            ->all();

        if ($fromInsights !== []) {
            return array_values(array_unique($fromInsights));
        }

        return DB::table('warehouse_item_monthly_stats')
            ->where('year', $year)
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->map(fn ($m) => (int) $m)
            ->filter(fn (int $m) => $m >= 1 && $m <= 12)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @param  list<int>  $monthsIncluded
     * @return array{year: int, month: int, rows: int, calculated_at: string}
     */
    private function storeRankings(
        int $year,
        int $month,
        array $aggregates,
        int $daysInPeriod,
        ?int $userId,
        array $monthsIncluded,
    ): array {
        $now = now();
        $daysForVelocity = max(1, $daysInPeriod);

        $ranked = [
            ItemInsightRanking::CATEGORY_BEST_SELLING => $this->rankBestSelling($aggregates),
            ItemInsightRanking::CATEGORY_MOST_PROFITABLE => $this->rankMostProfitable($aggregates),
            ItemInsightRanking::CATEGORY_LOSS_LEADER => $this->rankLossLeaders($aggregates),
            ItemInsightRanking::CATEGORY_FASTEST_SELLING => $this->rankFastestSelling($aggregates, $daysForVelocity),
        ];

        $payload = [];
        $totalRows = 0;

        foreach ($ranked as $category => $rows) {
            $rank = 0;
            foreach ($rows as $row) {
                $rank++;
                $payload[] = [
                    'year' => $year,
                    'month' => $month,
                    'category' => $category,
                    'rank' => $rank,
                    'item_id' => $row['item_id'],
                    'item_name' => $row['item_name'],
                    'item_code' => $row['item_code'],
                    'net_qty' => $row['net_qty'],
                    'net_value' => $row['net_value'],
                    'cost_total' => $row['cost_total'],
                    'profit' => $row['profit'],
                    'margin_pct' => $row['margin_pct'],
                    'daily_velocity' => $row['daily_velocity'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $totalRows++;
            }
        }

        DB::transaction(function () use ($year, $month, $payload, $totalRows, $userId, $now, $monthsIncluded): void {
            ItemInsightRanking::query()
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach (array_chunk($payload, 200) as $chunk) {
                ItemInsightRanking::query()->insert($chunk);
            }

            ItemInsightMonth::query()->updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'row_count' => $totalRows,
                    'months_included' => $monthsIncluded !== [] ? $monthsIncluded : null,
                    'calculated_by' => $userId,
                    'calculated_at' => $now,
                ],
            );
        });

        return [
            'year' => $year,
            'month' => $month,
            'rows' => $totalRows,
            'calculated_at' => $now->toDateTimeString(),
        ];
    }

    /**
     * @param  list<int>  $months
     * @return list<array<string, mixed>>
     */
    private function aggregateCompanyMonths(int $year, array $months): array
    {
        if ($months === []) {
            return [];
        }

        $rows = DB::table('warehouse_item_monthly_stats as w')
            ->join('items as i', 'i.id', '=', 'w.item_id')
            ->whereNull('i.deleted_at')
            ->where('w.year', $year)
            ->whereIn('w.month', $months)
            ->select([
                'w.item_id',
                'i.name as item_name',
                'i.code as item_code',
                'i.cost as unit_cost',
                'w.sold_qty',
                'w.returned_qty',
                'w.sold_value',
                'w.returned_value',
            ])
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $buckets[$itemId] ??= [
                'item_id' => $itemId,
                'item_name' => (string) $row->item_name,
                'item_code' => $row->item_code !== null ? (string) $row->item_code : null,
                'unit_cost' => (float) ($row->unit_cost ?? 0),
                'net_qty' => 0.0,
                'net_value' => 0.0,
            ];

            $buckets[$itemId]['net_qty'] += max(0.0, (float) $row->sold_qty - (float) $row->returned_qty);
            $buckets[$itemId]['net_value'] += max(0.0, (float) $row->sold_value - (float) $row->returned_value);
        }

        $aggregates = [];
        foreach ($buckets as $bucket) {
            $netQty = $bucket['net_qty'];
            $netValue = $bucket['net_value'];
            if ($netQty <= 0 && $netValue <= 0) {
                continue;
            }

            $costTotal = $netQty * $bucket['unit_cost'];
            $profit = $netValue - $costTotal;
            $marginPct = $netValue > 0 ? round($profit / $netValue * 100, 4) : null;

            $aggregates[] = [
                'item_id' => $bucket['item_id'],
                'item_name' => $bucket['item_name'],
                'item_code' => $bucket['item_code'],
                'net_qty' => $netQty,
                'net_value' => $netValue,
                'cost_total' => $costTotal,
                'profit' => $profit,
                'margin_pct' => $marginPct,
                'daily_velocity' => 0.0,
            ];
        }

        return $aggregates;
    }

    /**
     * @param  list<int>  $months
     */
    private function daysInMonths(int $year, array $months): int
    {
        $total = 0;
        foreach ($months as $month) {
            if ($month >= 1 && $month <= 12) {
                $total += Carbon::create($year, $month, 1)->daysInMonth;
            }
        }

        return max(0, $total);
    }

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    private function rankBestSelling(array $aggregates): array
    {
        usort($aggregates, function (array $a, array $b): int {
            return [$b['net_qty'], $b['net_value'], $b['item_id']]
                <=> [$a['net_qty'], $a['net_value'], $a['item_id']];
        });

        return array_slice($aggregates, 0, self::TOP_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    private function rankMostProfitable(array $aggregates): array
    {
        $candidates = array_values(array_filter(
            $aggregates,
            fn (array $row) => $row['profit'] > 0 && $row['net_qty'] > 0,
        ));

        usort($candidates, function (array $a, array $b): int {
            return [$b['profit'], $b['margin_pct'] ?? 0, $b['net_value']]
                <=> [$a['profit'], $a['margin_pct'] ?? 0, $a['net_value']];
        });

        return array_slice($candidates, 0, self::TOP_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    private function rankLossLeaders(array $aggregates): array
    {
        $candidates = array_values(array_filter(
            $aggregates,
            fn (array $row) => $row['profit'] < 0 && $row['net_qty'] >= self::LOSS_LEADER_MIN_QTY,
        ));

        usort($candidates, function (array $a, array $b): int {
            return [$a['profit'], $a['margin_pct'] ?? 0, $b['net_qty']]
                <=> [$b['profit'], $b['margin_pct'] ?? 0, $a['net_qty']];
        });

        return array_slice($candidates, 0, self::TOP_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    private function rankFastestSelling(array $aggregates, int $daysInPeriod): array
    {
        $days = max(1, $daysInPeriod);

        foreach ($aggregates as &$row) {
            $row['daily_velocity'] = round($row['net_qty'] / $days, 4);
        }
        unset($row);

        $candidates = array_values(array_filter($aggregates, fn (array $row) => $row['net_qty'] > 0));

        usort($candidates, function (array $a, array $b): int {
            return [$b['daily_velocity'], $b['net_qty'], $b['net_value']]
                <=> [$a['daily_velocity'], $a['net_qty'], $a['net_value']];
        });

        return array_slice($candidates, 0, self::TOP_LIMIT);
    }
}
