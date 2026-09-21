<?php

namespace App\Services;

use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use Illuminate\Support\Collection;

class ItemInsightQueryService
{
    public const GRAIN_MONTH = 'month';

    public const GRAIN_YEAR = 'year';

    public function latestCalculatedMonthlyPeriod(): ?ItemInsightMonth
    {
        return ItemInsightMonth::query()
            ->whereBetween('month', [1, 12])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
    }

    public function latestCalculatedYearlyPeriod(): ?ItemInsightMonth
    {
        return ItemInsightMonth::query()
            ->where('month', ItemInsightMonth::MONTH_YEARLY)
            ->orderByDesc('year')
            ->first();
    }

    /**
     * @return Collection<int, ItemInsightMonth>
     */
    public function calculatedMonths(): Collection
    {
        return ItemInsightMonth::query()
            ->whereBetween('month', [1, 12])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    /**
     * @return Collection<int, ItemInsightMonth>
     */
    public function calculatedYears(): Collection
    {
        return ItemInsightMonth::query()
            ->where('month', ItemInsightMonth::MONTH_YEARLY)
            ->orderByDesc('year')
            ->get();
    }

    public function isCalculated(int $year, int $month): bool
    {
        return ItemInsightMonth::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    public function periodMeta(int $year, int $month): ?ItemInsightMonth
    {
        return ItemInsightMonth::query()
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * @return Collection<int, ItemInsightRanking>
     */
    public function rankingsFor(int $year, int $month, string $category): Collection
    {
        if (! in_array($category, ItemInsightRanking::validCategories(), true)) {
            $category = ItemInsightRanking::CATEGORY_BEST_SELLING;
        }

        return ItemInsightRanking::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('category', $category)
            ->orderBy('rank')
            ->get();
    }

    public function normalizeGrain(?string $raw): string
    {
        $grain = (string) ($raw ?? self::GRAIN_MONTH);

        return in_array($grain, [self::GRAIN_MONTH, self::GRAIN_YEAR], true)
            ? $grain
            : self::GRAIN_MONTH;
    }

    /**
     * @return array{grain: string, year: int, month: int, period_key: string}
     */
    public function resolveView(?string $periodParam, ?string $grainParam): array
    {
        $grain = $this->normalizeGrain($grainParam);

        if ($periodParam && preg_match('/^(\d{4})$/', $periodParam, $matches)) {
            $year = (int) $matches[1];

            return [
                'grain' => self::GRAIN_YEAR,
                'year' => $year,
                'month' => ItemInsightMonth::MONTH_YEARLY,
                'period_key' => sprintf('%04d', $year),
            ];
        }

        if ($periodParam && preg_match('/^(\d{4})-(\d{2})$/', $periodParam, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12) {
                return [
                    'grain' => self::GRAIN_MONTH,
                    'year' => $year,
                    'month' => $month,
                    'period_key' => sprintf('%04d-%02d', $year, $month),
                ];
            }
        }

        if ($grain === self::GRAIN_YEAR) {
            $latest = $this->latestCalculatedYearlyPeriod();
            if ($latest) {
                return [
                    'grain' => self::GRAIN_YEAR,
                    'year' => $latest->year,
                    'month' => ItemInsightMonth::MONTH_YEARLY,
                    'period_key' => $latest->periodLabel(),
                ];
            }

            return [
                'grain' => self::GRAIN_YEAR,
                'year' => (int) now()->year,
                'month' => ItemInsightMonth::MONTH_YEARLY,
                'period_key' => (string) now()->year,
            ];
        }

        $latest = $this->latestCalculatedMonthlyPeriod();
        if ($latest) {
            return [
                'grain' => self::GRAIN_MONTH,
                'year' => $latest->year,
                'month' => $latest->month,
                'period_key' => $latest->periodLabel(),
            ];
        }

        $previous = now()->subMonth();

        return [
            'grain' => self::GRAIN_MONTH,
            'year' => (int) $previous->year,
            'month' => (int) $previous->month,
            'period_key' => $previous->format('Y-m'),
        ];
    }

    public function normalizeCategory(?string $raw): string
    {
        $category = (string) ($raw ?? ItemInsightRanking::CATEGORY_BEST_SELLING);

        return in_array($category, ItemInsightRanking::validCategories(), true)
            ? $category
            : ItemInsightRanking::CATEGORY_BEST_SELLING;
    }

    /**
     * @param  list<int>  $months
     */
    public function formatMonthsIncluded(array $months): string
    {
        $months = array_values(array_unique(array_filter($months, fn (int $m) => $m >= 1 && $m <= 12)));
        sort($months);
        if ($months === []) {
            return 'no months';
        }

        return implode(', ', array_map(fn (int $m) => sprintf('%02d', $m), $months));
    }
}
