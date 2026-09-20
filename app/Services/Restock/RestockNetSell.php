<?php

namespace App\Services\Restock;

/**
 * Company net sell (sell − return), aligned with ItemInsightSyncService::aggregateCompanyMonths.
 */
final class RestockNetSell
{
    /**
     * Net units per calendar month from one warehouse_item_monthly_stats row.
     */
    public static function netQtyFromStatRow(float $soldQty, float $returnedQty): float
    {
        return max(0.0, $soldQty - $returnedQty);
    }

    /**
     * Annualize a health-window net total to an average calendar month (30-day month).
     */
    public static function monthlyRateFromPeriod(float $netPeriod, int $periodDays): float
    {
        $periodDays = max(1, $periodDays);

        return ($netPeriod / $periodDays) * 30;
    }

    /**
     * @param  list<float>  $monthlyNetQty  oldest → newest
     */
    public static function meanMonthlyNet(array $monthlyNetQty): float
    {
        $values = array_values($monthlyNetQty);
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }
}
