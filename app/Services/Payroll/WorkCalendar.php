<?php

namespace App\Services\Payroll;

use App\Models\Cuti;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class WorkCalendar
{
    /**
     * ISO weekdays that count as regular work days. 1 = Monday … 7 = Sunday.
     * Default is Monday–Saturday; Sunday is off unless the employee punches in
     * (those hours still count as actual, so a Sunday shift offsets a weekday off).
     *
     * @return list<int>
     */
    public function workWeekdays(): array
    {
        $raw = Setting::getValue('payroll.hari_kerja', '1,2,3,4,5,6');

        if (is_array($raw)) {
            $days = array_map('intval', $raw);
        } else {
            $days = array_map('intval', preg_split('/\s*,\s*/', (string) $raw) ?: []);
        }

        $days = array_values(array_unique(array_filter(
            $days,
            fn (int $day) => $day >= 1 && $day <= 7,
        )));

        return $days !== [] ? $days : [1, 2, 3, 4, 5, 6];
    }

    /**
     * @return array<string, string> Y-m-d => holiday name
     */
    public function holidaysBetween(Carbon|string $start, Carbon|string $end): array
    {
        $from = Carbon::parse($start)->toDateString();
        $to = Carbon::parse($end)->toDateString();

        return HariLibur::query()
            ->whereDate('tanggal', '>=', $from)
            ->whereDate('tanggal', '<=', $to)
            ->orderBy('tanggal')
            ->pluck('nama', 'tanggal')
            ->mapWithKeys(fn ($nama, $tanggal) => [Carbon::parse($tanggal)->toDateString() => (string) $nama])
            ->all();
    }

    public function isWorkDay(Carbon|string $date, ?array $holidays = null): bool
    {
        $day = Carbon::parse($date)->startOfDay();
        $holidays ??= $this->holidaysBetween($day, $day);

        if (isset($holidays[$day->toDateString()])) {
            return false;
        }

        return in_array($day->isoWeekday(), $this->workWeekdays(), true);
    }

    /**
     * @param  list<string>|array<string, mixed>  $dates  Y-m-d values
     * @return list<string>
     */
    public function workDatesAmong(array $dates): array
    {
        $normalized = [];
        foreach ($dates as $date) {
            $key = Carbon::parse((string) $date)->toDateString();
            $normalized[$key] = $key;
        }

        if ($normalized === []) {
            return [];
        }

        ksort($normalized);
        $keys = array_values($normalized);
        $holidays = $this->holidaysBetween($keys[0], $keys[array_key_last($keys)]);

        return array_values(array_filter(
            $keys,
            fn (string $date) => $this->isWorkDay($date, $holidays),
        ));
    }

    /**
     * Leave days that fall on expected work days in the given date set.
     *
     * @param  list<string>  $dates
     */
    public function leaveWorkDaysAmong(Karyawan $karyawan, array $dates): int
    {
        $workDates = array_flip($this->workDatesAmong($dates));
        if ($workDates === []) {
            return 0;
        }

        $sorted = array_keys($workDates);
        sort($sorted);
        $rangeStart = $sorted[0];
        $rangeEnd = $sorted[array_key_last($sorted)];

        $cutis = Cuti::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereDate('tgl_mulai', '<=', $rangeEnd)
            ->whereDate('tgl_akhir', '>=', $rangeStart)
            ->get();

        $counted = [];
        foreach ($cutis as $cuti) {
            $period = CarbonPeriod::create(
                Carbon::parse($cuti->tgl_mulai)->startOfDay(),
                Carbon::parse($cuti->tgl_akhir)->startOfDay(),
            );
            foreach ($period as $day) {
                $key = $day->toDateString();
                if (isset($workDates[$key])) {
                    $counted[$key] = true;
                }
            }
        }

        return count($counted);
    }
}
