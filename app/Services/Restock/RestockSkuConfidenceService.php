<?php

namespace App\Services\Restock;

use App\Models\Transaction;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\ItemInsightLastBuyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RestockSkuConfidenceService
{
    public const PATTERN_STABLE = 'stable_replenishment';

    public const PATTERN_SPIKE = 'spike_opportunity';

    public const PATTERN_FATIGUE = 'fatigue_hold';

    public const PATTERN_MODERATE = 'moderate';

    /** Sustained or recent velocity at or above this net units / calendar month. */
    public const PATTERN_HERO = 'hero_product';

    public const PATTERN_FAST = 'fast_seller';

    public const PATTERN_MEDIUM = 'medium_velocity';

    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    /** Max coefficient of variation (std/mean) for “steady” monthly demand. */
    public const STABLE_CV_MAX = 0.85;

    public const STABLE_MIN_ACTIVE_MONTHS = 6;

    public const STABLE_MIN_MEAN_MONTHLY_NET = 1.0;

    /** Recent daily sell rate vs trailing-year daily rate. */
    public const SPIKE_ACCELERATION_MIN = 1.8;

    public const SPIKE_MIN_PERIOD_NET = 3.0;

    /** Latest 3-month average below this fraction of 12m peak → interest fading. */
    public const FATIGUE_VS_PEAK_RATIO = 0.7;

    public const FATIGUE_MIN_PEAK_NET = 5.0;

    /** Sold less than this fraction of last buy qty after expected sell-through window. */
    public const FATIGUE_SELL_THROUGH_RATIO = 0.5;

    public const FATIGUE_SELL_THROUGH_SLOW_FACTOR = 2.0;

    public function __construct(
        private readonly ItemInsightLastBuyResolver $lastBuys,
    ) {}

    /**
     * @param  list<int>  $itemIds
     * @param  array<int, array{net_period: float, period_days: int, stock_qty: float, days_of_cover: ?float, health_key: ?string}>  $contextByItem
     * @return array<int, array{pattern: string, pattern_label: string, confidence: string, confidence_label: string, detail: string}>
     */
    public function forItems(array $itemIds, array $contextByItem, ?\DateTimeInterface $asOf = null): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $asOf = Carbon::parse($asOf ?? now());
        $monthlyByItem = $this->monthlyNetSeriesByItem($itemIds, $asOf);
        $lastBuyByItem = $this->lastBuys->forItems($itemIds, $asOf);
        $soldSinceBuy = $this->netSoldSinceLastBuy($itemIds, $lastBuyByItem, $asOf->toDateString());

        $out = [];
        foreach ($itemIds as $itemId) {
            $context = $contextByItem[$itemId] ?? [
                'net_period' => 0.0,
                'period_days' => 30,
                'stock_qty' => 0.0,
                'days_of_cover' => null,
                'health_key' => null,
            ];

            $out[$itemId] = $this->classify(
                $monthlyByItem[$itemId] ?? [],
                (float) $context['net_period'],
                max(1, (int) $context['period_days']),
                (float) $context['stock_qty'],
                $context['days_of_cover'] !== null ? (float) $context['days_of_cover'] : null,
                is_string($context['health_key'] ?? null) ? $context['health_key'] : null,
                $lastBuyByItem[$itemId] ?? null,
                (float) ($soldSinceBuy[$itemId] ?? 0.0),
                $asOf,
            );
        }

        return $out;
    }

    /**
     * @param  list<float>  $monthlyNetQty  oldest → newest (up to 12 months)
     * @param  array{qty: float, date: string}|null  $lastBuy
     * @return array{pattern: string, pattern_label: string, confidence: string, confidence_label: string, detail: string}
     */
    public function classify(
        array $monthlyNetQty,
        float $netPeriod,
        int $periodDays,
        float $stock,
        ?float $daysOfCover,
        ?string $healthKey,
        ?array $lastBuy,
        float $soldSinceLastBuy,
        ?\DateTimeInterface $asOf = null,
    ): array {
        $asOfDay = Carbon::parse($asOf ?? now())->startOfDay();
        $monthlyNetQty = array_map(fn ($v) => max(0.0, (float) $v), $monthlyNetQty);
        $stats = $this->seriesStats($monthlyNetQty);
        $monthlyFromPeriod = RestockNetSell::monthlyRateFromPeriod($netPeriod, $periodDays);
        $meanMonthly = $stats['mean'];
        $dailyRecent = $netPeriod / max(1, $periodDays);
        $dailyBaseline = $meanMonthly > 0
            ? $meanMonthly / 30
            : 0.0;
        $acceleration = $dailyBaseline > 0.01
            ? $dailyRecent / $dailyBaseline
            : ($dailyRecent > 0 ? 99.0 : 0.0);

        $fatigueDetail = $this->fatigueReason(
            $monthlyNetQty,
            $stats,
            $lastBuy,
            $soldSinceLastBuy,
            $dailyRecent,
            $healthKey,
            $daysOfCover,
            $acceleration,
            $asOfDay,
        );

        if ($fatigueDetail !== null) {
            return $this->result(self::PATTERN_FATIGUE, self::CONFIDENCE_LOW, $fatigueDetail);
        }

        $velocityPattern = $this->velocityPattern($meanMonthly, $monthlyFromPeriod);
        if ($velocityPattern !== null) {
            $confidence = $this->lowCover($daysOfCover, $healthKey)
                ? self::CONFIDENCE_HIGH
                : self::CONFIDENCE_MEDIUM;

            return $this->result(
                $velocityPattern['pattern'],
                $confidence,
                $velocityPattern['detail'],
            );
        }

        $isStable = $this->isStableReplenishment($monthlyNetQty, $stats, $acceleration);
        if ($isStable) {
            $confidence = $this->lowCover($daysOfCover, $healthKey)
                ? self::CONFIDENCE_HIGH
                : self::CONFIDENCE_MEDIUM;

            return $this->result(
                self::PATTERN_STABLE,
                $confidence,
                sprintf(
                    'Steady demand (CV %s, %d active months in 12m).',
                    number_format($stats['cv'], 2),
                    $stats['active_months'],
                ),
            );
        }

        $isSpike = $acceleration >= self::SPIKE_ACCELERATION_MIN
            && $netPeriod >= self::SPIKE_MIN_PERIOD_NET;

        if ($isSpike) {
            $confidence = $this->lowCover($daysOfCover, $healthKey)
                ? self::CONFIDENCE_HIGH
                : self::CONFIDENCE_MEDIUM;

            return $this->result(
                self::PATTERN_SPIKE,
                $confidence,
                sprintf(
                    'Recent sell rate ≈ %s× the 12-month average (acceleration).',
                    number_format($acceleration, 1),
                ),
            );
        }

        $confidence = $this->lowCover($daysOfCover, $healthKey)
            ? self::CONFIDENCE_MEDIUM
            : self::CONFIDENCE_LOW;

        return $this->result(
            self::PATTERN_MODERATE,
            $confidence,
            'Mixed demand history — size the next buy conservatively.',
        );
    }

    /**
     * @param  list<float>  $monthlyNetQty
     * @return array{sum: float, mean: float, std: float, cv: float, active_months: int, peak: float}
     */
    private function seriesStats(array $monthlyNetQty): array
    {
        $values = array_values($monthlyNetQty);
        $count = count($values);
        $sum = array_sum($values);
        $mean = $count > 0 ? $sum / $count : 0.0;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $std = $count > 1 ? sqrt($variance / $count) : 0.0;
        $cv = $mean > 0 ? $std / $mean : ($std > 0 ? 99.0 : 0.0);
        $activeMonths = count(array_filter($values, fn (float $v) => $v > 0));

        return [
            'sum' => $sum,
            'mean' => $mean,
            'std' => $std,
            'cv' => $cv,
            'active_months' => $activeMonths,
            'peak' => $count > 0 ? max($values) : 0.0,
        ];
    }

    /**
     * @param  list<float>  $monthlyNetQty
     * @param  array{sum: float, mean: float, std: float, cv: float, active_months: int, peak: float}  $stats
     */
    /**
     * @return array{pattern: string, detail: string}|null
     */
    private function velocityPattern(float $meanMonthlyNet, float $monthlyFromHealthPeriod): ?array
    {
        $rate = RestockNetSell::effectiveMonthlyRate($monthlyFromHealthPeriod, $meanMonthlyNet);
        $tier = RestockNetSell::velocityTier($rate);
        $detail = sprintf(
            '%s — ≈%s units/mo (health window), %s units/mo avg (12m).',
            RestockNetSell::velocityTierLabels()[$tier],
            number_format($monthlyFromHealthPeriod, 1),
            number_format($meanMonthlyNet, 1),
        );

        return match ($tier) {
            RestockNetSell::TIER_HERO => ['pattern' => self::PATTERN_HERO, 'detail' => $detail],
            RestockNetSell::TIER_FAST => ['pattern' => self::PATTERN_FAST, 'detail' => $detail],
            RestockNetSell::TIER_MEDIUM => ['pattern' => self::PATTERN_MEDIUM, 'detail' => $detail],
            default => null,
        };
    }

    private function isStableReplenishment(array $monthlyNetQty, array $stats, float $acceleration): bool
    {
        if ($stats['active_months'] < self::STABLE_MIN_ACTIVE_MONTHS) {
            return false;
        }
        if ($stats['mean'] < self::STABLE_MIN_MEAN_MONTHLY_NET) {
            return false;
        }
        if ($stats['cv'] > self::STABLE_CV_MAX) {
            return false;
        }

        return $acceleration < self::SPIKE_ACCELERATION_MIN;
    }

    /**
     * @param  list<float>  $monthlyNetQty
     * @param  array{sum: float, mean: float, std: float, cv: float, active_months: int, peak: float}  $stats
     * @param  array{qty: float, date: string}|null  $lastBuy
     */
    private function fatigueReason(
        array $monthlyNetQty,
        array $stats,
        ?array $lastBuy,
        float $soldSinceLastBuy,
        float $dailyRecent,
        ?string $healthKey,
        ?float $daysOfCover,
        float $acceleration,
        Carbon $asOfDay,
    ): ?string {
        if ($healthKey === InventoryHealthClassifier::OVERSTOCK) {
            return 'Inventory health: overstock — pause replenishment.';
        }

        if ($healthKey === InventoryHealthClassifier::DEAD) {
            return 'No net sales in the extended window — avoid restocking.';
        }

        $values = array_values($monthlyNetQty);
        $count = count($values);
        if ($count >= 3 && $stats['peak'] >= self::FATIGUE_MIN_PEAK_NET) {
            $lastThree = array_slice($values, -3);
            $avgRecent = array_sum($lastThree) / 3;
            if ($avgRecent < self::FATIGUE_VS_PEAK_RATIO * $stats['peak']) {
                return sprintf(
                    'Demand fading: last 3 months avg %s vs peak %s units/month.',
                    number_format($avgRecent, 1),
                    number_format($stats['peak'], 1),
                );
            }
        }

        if ($lastBuy !== null && (float) $lastBuy['qty'] > 0 && $dailyRecent > 0) {
            $buyQty = (float) $lastBuy['qty'];
            $expectedDays = $buyQty / $dailyRecent;
            $buyDate = Carbon::parse($lastBuy['date'])->startOfDay();
            $daysSince = max(0, $buyDate->diffInDays($asOfDay));
            $slowThreshold = $expectedDays * self::FATIGUE_SELL_THROUGH_SLOW_FACTOR;

            if ($daysSince >= $slowThreshold
                && $soldSinceLastBuy < self::FATIGUE_SELL_THROUGH_RATIO * $buyQty) {
                return sprintf(
                    'Last buy (%s units on %s) is slow to clear: %s sold in %s days (expected ~%s days).',
                    number_format($buyQty, 0),
                    $lastBuy['date'],
                    number_format($soldSinceLastBuy, 0),
                    number_format($daysSince, 0),
                    number_format($expectedDays, 0),
                );
            }
        }

        if ($daysOfCover !== null
            && $daysOfCover > InventoryHealthClassifier::OVERSTOCK_COVER_DAYS
            && $acceleration < 1.0
            && $stats['peak'] >= self::FATIGUE_MIN_PEAK_NET) {
            return sprintf(
                'High cover (≈%s days) and sell rate below the 12-month average after a prior demand peak.',
                number_format($daysOfCover, 0),
            );
        }

        return null;
    }

    private function lowCover(?float $daysOfCover, ?string $healthKey): bool
    {
        if ($healthKey === InventoryHealthClassifier::LOW) {
            return true;
        }

        return $daysOfCover !== null && $daysOfCover < InventoryHealthClassifier::LOW_COVER_DAYS;
    }

    /**
     * @return array{pattern: string, pattern_label: string, confidence: string, confidence_label: string, detail: string}
     */
    private function result(string $pattern, string $confidence, string $detail): array
    {
        return [
            'pattern' => $pattern,
            'pattern_label' => self::patternLabels()[$pattern] ?? $pattern,
            'confidence' => $confidence,
            'confidence_label' => self::confidenceLabels()[$confidence] ?? $confidence,
            'detail' => $detail,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function patternLabels(): array
    {
        return [
            self::PATTERN_HERO => 'Hero product',
            self::PATTERN_FAST => 'Fast seller',
            self::PATTERN_MEDIUM => 'Medium velocity',
            self::PATTERN_STABLE => 'Stable replenishment',
            self::PATTERN_SPIKE => 'Spike opportunity',
            self::PATTERN_FATIGUE => 'Fatigue — hold',
            self::PATTERN_MODERATE => 'Moderate',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function confidenceLabels(): array
    {
        return [
            self::CONFIDENCE_HIGH => 'High confidence',
            self::CONFIDENCE_MEDIUM => 'Medium confidence',
            self::CONFIDENCE_LOW => 'Low confidence',
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, list<float>>
     */
    private function monthlyNetSeriesByItem(array $itemIds, \DateTimeInterface $asOf): array
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

    /**
     * @param  array<int, array{qty: float, date: string}>  $lastBuyByItem
     * @return array<int, float>
     */
    private function netSoldSinceLastBuy(array $itemIds, array $lastBuyByItem, string $asOfDate): array
    {
        $sell = Transaction::TYPE_SELL;
        $return = Transaction::TYPE_RETURN;
        $out = [];

        foreach ($itemIds as $itemId) {
            $buy = $lastBuyByItem[$itemId] ?? null;
            if ($buy === null) {
                $out[$itemId] = 0.0;

                continue;
            }

            $from = $buy['date'];
            $row = DB::table('transaction_details')
                ->where('item_id', $itemId)
                ->whereIn('transaction_type', [$sell, $return])
                ->where('date', '>=', $from)
                ->where('date', '<=', $asOfDate)
                ->where('date', '>', '0000-00-00')
                ->selectRaw(
                    'SUM(CASE WHEN transaction_type = ? THEN ABS(quantity) ELSE 0 END) - SUM(CASE WHEN transaction_type = ? THEN ABS(quantity) ELSE 0 END) as net',
                    [$sell, $return],
                )
                ->value('net');

            $out[$itemId] = max(0.0, (float) ($row ?? 0));
        }

        return $out;
    }
}
