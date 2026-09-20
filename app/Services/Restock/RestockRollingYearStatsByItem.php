<?php

namespace App\Services\Restock;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolling 12 calendar months of company net sell from warehouse_item_monthly_stats
 * (≈365 days, no transaction_details scan).
 */
final class RestockRollingYearStatsByItem
{
    /**
     * @param  list<int>  $itemIds
     * @return array<int, array{net_12m: float, monthly_avg: float, monthly_series: list<float>}>
     */
    public function summariesForItems(array $itemIds, ?\DateTimeInterface $asOf = null): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $seriesByItem = $this->monthlyNetSeriesByItem($itemIds, $asOf ?? now());
        $out = [];
        foreach ($itemIds as $itemId) {
            $series = $seriesByItem[$itemId] ?? [];
            $net12 = array_sum($series);
            $out[$itemId] = [
                'net_12m' => $net12,
                'monthly_avg' => RestockNetSell::meanMonthlyNet($series),
                'monthly_series' => $series,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, list<float>>
     */
    public function monthlyNetSeriesByItem(array $itemIds, \DateTimeInterface $asOf): array
    {
        $asOf = Carbon::parse($asOf);
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $cursor = $asOf->copy()->startOfMonth()->subMonths($i);
            $months[] = [(int) $cursor->year, (int) $cursor->month];
        }

        $startKey = $months[0][0] * 12 + $months[0][1];

        $rows = DB::table('warehouse_item_monthly_stats')
            ->whereIn('item_id', $itemIds)
            ->whereRaw('(year * 12 + month) >= ?', [$startKey])
            ->get(['item_id', 'year', 'month', 'sold_qty', 'returned_qty']);

        $bucket = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $key = (int) $row->year * 12 + (int) $row->month;
            $net = RestockNetSell::netQtyFromStatRow((float) $row->sold_qty, (float) $row->returned_qty);
            $bucket[$itemId][$key] = ($bucket[$itemId][$key] ?? 0.0) + $net;
        }

        $series = [];
        foreach ($itemIds as $itemId) {
            $line = [];
            foreach ($months as [$year, $month]) {
                $key = $year * 12 + $month;
                $line[] = (float) ($bucket[$itemId][$key] ?? 0.0);
            }
            $series[$itemId] = $line;
        }

        return $series;
    }
}
