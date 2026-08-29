<?php

namespace App\Services\Reporting;

use Illuminate\Support\Carbon;

class ReportingPeriod
{
    public static function monthStart(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)->startOfDay();
    }

    public static function monthEnd(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
    }

    /**
     * Month-end as-of date. The current calendar month uses today (partial month).
     */
    public static function asOf(int $year, int $month, ?\DateTimeInterface $now = null): Carbon
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        $end = self::monthEnd($year, $month);

        if ($now->year === $year && $now->month === $month) {
            return $now->lt($end) ? $now : $end;
        }

        return $end;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function monthRange(int $year, int $month): array
    {
        $start = self::monthStart($year, $month);

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    public static function previousMonth(int $year, int $month): array
    {
        $date = self::monthStart($year, $month)->subMonth();

        return [$date->year, $date->month];
    }

    /**
     * Calendar months ending at $year/$month (oldest first).
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function monthsEnding(int $year, int $month, int $count): array
    {
        $count = max(1, $count);
        $cursor = self::monthStart($year, $month);
        $months = [];

        for ($i = 0; $i < $count; $i++) {
            $months[] = [$cursor->year, $cursor->month];
            $cursor = $cursor->copy()->subMonth();
        }

        return array_reverse($months);
    }

    /**
     * Inclusive date range covering the first through last month pair.
     *
     * @param  list<array{0: int, 1: int}>  $months
     * @return array{0: string, 1: string}
     */
    public static function spanRange(array $months): array
    {
        $first = $months[0];
        $last = $months[array_key_last($months)];

        return [
            self::monthStart($first[0], $first[1])->toDateString(),
            self::monthEnd($last[0], $last[1])->toDateString(),
        ];
    }
}
