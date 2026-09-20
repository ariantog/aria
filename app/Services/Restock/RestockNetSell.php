<?php

namespace App\Services\Restock;

/**
 * Company net sell (sell − return), aligned with ItemInsightSyncService::aggregateCompanyMonths.
 */
final class RestockNetSell
{
    public const VELOCITY_HERO_MIN = 100.0;

    public const VELOCITY_FAST_MIN = 50.0;

    public const VELOCITY_MEDIUM_MIN = 30.0;

    public const TIER_HERO = 'hero';

    public const TIER_FAST = 'fast';

    public const TIER_MEDIUM = 'medium';

    public const TIER_BELOW = 'below_medium';

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

    /**
     * Classify net units per calendar month (strict thresholds: hero &gt; 100, fast &gt; 50, medium &gt; 30).
     */
    public static function velocityTier(float $monthlyNet): string
    {
        if ($monthlyNet > self::VELOCITY_HERO_MIN) {
            return self::TIER_HERO;
        }
        if ($monthlyNet > self::VELOCITY_FAST_MIN) {
            return self::TIER_FAST;
        }
        if ($monthlyNet > self::VELOCITY_MEDIUM_MIN) {
            return self::TIER_MEDIUM;
        }

        return self::TIER_BELOW;
    }

    /**
     * Use the stronger of the health-window rate and 12-month average for tiering.
     */
    public static function effectiveMonthlyRate(float $monthlyFromHealthPeriod, float $meanMonthly12m): float
    {
        return max($monthlyFromHealthPeriod, $meanMonthly12m);
    }

    /**
     * @return array<string, string>
     */
    public static function velocityTierLabels(): array
    {
        return [
            self::TIER_HERO => 'Hero (>100/mo)',
            self::TIER_FAST => 'Fast (>50/mo)',
            self::TIER_MEDIUM => 'Medium (>30/mo)',
            self::TIER_BELOW => 'Below medium',
        ];
    }
}
