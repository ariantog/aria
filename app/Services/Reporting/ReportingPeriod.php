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
    public static function asOf(int $year, int $month, ?Carbon $now = null): Carbon
    {
        $now = ($now ?? now())->copy()->startOfDay();
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
}
