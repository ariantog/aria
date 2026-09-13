<?php

namespace App\Services;

use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use Illuminate\Support\Collection;

class ItemInsightQueryService
{
    public function latestCalculatedPeriod(): ?ItemInsightMonth
    {
        return ItemInsightMonth::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
    }

    /**
     * @return Collection<int, ItemInsightMonth>
     */
    public function calculatedMonths(): Collection
    {
        return ItemInsightMonth::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    public function isCalculated(int $year, int $month): bool
    {
        return ItemInsightMonth::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    public function monthMeta(int $year, int $month): ?ItemInsightMonth
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

    /**
     * @return array{year: int, month: int}
     */
    public function resolvePeriod(?string $periodParam): array
    {
        if ($periodParam && preg_match('/^(\d{4})-(\d{2})$/', $periodParam, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12) {
                return ['year' => $year, 'month' => $month];
            }
        }

        $latest = $this->latestCalculatedPeriod();
        if ($latest) {
            return ['year' => $latest->year, 'month' => $latest->month];
        }

        $previous = now()->subMonth();

        return ['year' => (int) $previous->year, 'month' => (int) $previous->month];
    }

    public function normalizeCategory(?string $raw): string
    {
        $category = (string) ($raw ?? ItemInsightRanking::CATEGORY_BEST_SELLING);

        return in_array($category, ItemInsightRanking::validCategories(), true)
            ? $category
            : ItemInsightRanking::CATEGORY_BEST_SELLING;
    }
}
