<?php

namespace App\Services\Payroll;

use App\Models\Cuti;
use App\Models\Karyawan;
use App\Models\Setting;
use Carbon\Carbon;

class GajiSalaryCalculator
{
    public const WORKING_DAYS_PER_MONTH = 26;

    /**
     * @return array{
     *   bulan: int,
     *   tahun: int,
     *   bulanan: int,
     *   harian_rate: int,
     *   harian_total: int,
     *   cuti_tahunan: int,
     *   cuti_sakit: int,
     *   cuti_mendadak: int,
     *   hari_izin: int,
     *   total_cuti: int,
     *   denda_cuti_tahunan: int,
     *   denda_cuti_sakit: int,
     *   potongan_harian: int,
     *   menit_telat: int,
     *   potongan_telat: int,
     *   jam_lembur: float,
     *   upah_lembur: int,
     *   total_potongan: int,
     *   bonus: int,
     *   sanksi: int,
     *   total_gaji: int,
     *   limit_tahunan: int,
     *   limit_sakit: int,
     *   running_tahunan_before: int,
     *   running_sakit_before: int,
     *   grace_period_menit: int,
     *   jam_kerja_per_hari: int,
     *   lembur_multiplier: float,
     * }
     */
    public function calculate(
        Karyawan $karyawan,
        int $bulan,
        int $tahun,
        int $bonus = 0,
        int $sanksi = 0,
        ?int $overridePotongHarian = null,
        ?int $overridePotongTelat = null,
        ?int $overrideUpahLembur = null,
        ?int $overrideCutiTahunan = null,
        ?int $overrideCutiSakit = null,
        ?int $overrideCutiMendadak = null,
        ?int $overrideHariIzin = null,
        ?int $overrideMenitTelat = null,
        ?float $overrideJamLembur = null,
    ): array {
        $limitTahunan = (int) Setting::getValue('batas_cuti_tahunan', 12);
        $limitSakit = (int) Setting::getValue('batas_cuti_sakit', 30);
        $gracePeriod = $this->gracePeriodFor($karyawan);
        $jamKerjaPerHari = (int) Setting::getValue('payroll.jam_kerja_per_hari', 8);
        $lemburMultiplier = (float) Setting::getValue('payroll.lembur_multiplier', 1.5);

        $running = $this->runningCutiTotals($karyawan, $tahun, $bulan);

        $cutiCounts = $this->cutiDaysForMonth($karyawan, $tahun, $bulan);

        if ($overrideCutiTahunan !== null) {
            $cutiCounts['tahunan'] = max(0, $overrideCutiTahunan);
        }
        if ($overrideCutiSakit !== null) {
            $cutiCounts['sakit'] = max(0, $overrideCutiSakit);
        }
        if ($overrideCutiMendadak !== null) {
            $cutiCounts['mendadak'] = max(0, $overrideCutiMendadak);
        }

        $hariIzin = $overrideHariIzin ?? $cutiCounts['izin'];

        $dendaTahunan = $this->excessCutiDays(
            $running['tahunan'],
            $cutiCounts['tahunan'],
            $limitTahunan,
        );
        $dendaSakit = $this->excessCutiDays(
            $running['sakit'],
            $cutiCounts['sakit'],
            $limitSakit,
        );

        $harianRate = (int) $karyawan->harian;
        $defaultPotongHarian = ($dendaTahunan + $dendaSakit + $cutiCounts['mendadak'] + $hariIzin) * $harianRate;
        $potongHarian = $overridePotongHarian ?? $defaultPotongHarian;

        $menitTelat = $overrideMenitTelat ?? 0;
        $defaultPotongTelat = $this->calculateTelatPotongan($karyawan, $menitTelat, $harianRate, $gracePeriod, $jamKerjaPerHari);
        $potongTelat = $overridePotongTelat ?? $defaultPotongTelat;

        $jamLembur = $overrideJamLembur ?? 0.0;
        $defaultUpahLembur = $this->calculateUpahLembur($jamLembur, $harianRate, $jamKerjaPerHari, $lemburMultiplier);
        $upahLembur = $overrideUpahLembur ?? $defaultUpahLembur;

        $bulanan = (int) $karyawan->bulanan;
        $harianTotal = $harianRate * self::WORKING_DAYS_PER_MONTH;
        $totalPotongan = $potongHarian + $potongTelat + $sanksi;
        $totalGaji = $bulanan + $harianTotal + $bonus + $upahLembur - $totalPotongan;

        $totalCuti = $cutiCounts['tahunan'] + $cutiCounts['sakit'] + $cutiCounts['mendadak'];

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanan' => $bulanan,
            'harian_rate' => $harianRate,
            'harian_total' => $harianTotal,
            'cuti_tahunan' => $cutiCounts['tahunan'],
            'cuti_sakit' => $cutiCounts['sakit'],
            'cuti_mendadak' => $cutiCounts['mendadak'],
            'hari_izin' => $hariIzin,
            'total_cuti' => $totalCuti,
            'denda_cuti_tahunan' => $dendaTahunan,
            'denda_cuti_sakit' => $dendaSakit,
            'potongan_harian' => $potongHarian,
            'menit_telat' => $menitTelat,
            'potongan_telat' => $potongTelat,
            'jam_lembur' => $jamLembur,
            'upah_lembur' => $upahLembur,
            'total_potongan' => $totalPotongan,
            'bonus' => $bonus,
            'sanksi' => $sanksi,
            'total_gaji' => $totalGaji,
            'limit_tahunan' => $limitTahunan,
            'limit_sakit' => $limitSakit,
            'running_tahunan_before' => $running['tahunan'],
            'running_sakit_before' => $running['sakit'],
            'grace_period_menit' => $gracePeriod,
            'jam_kerja_per_hari' => $jamKerjaPerHari,
            'lembur_multiplier' => $lemburMultiplier,
        ];
    }

    public function gracePeriodFor(Karyawan $karyawan): int
    {
        if ($karyawan->grace_period_menit !== null) {
            return (int) $karyawan->grace_period_menit;
        }

        return (int) Setting::getValue('payroll.grace_period_menit', 15);
    }

    public function calculateTelatPotongan(
        Karyawan $karyawan,
        int $menitTelat,
        int $harianRate,
        ?int $gracePeriod = null,
        ?int $jamKerjaPerHari = null,
    ): int {
        if (! (bool) ($karyawan->waktu_dibatasi ?? true)) {
            return 0;
        }

        if ($menitTelat <= 0 || $harianRate <= 0) {
            return 0;
        }

        $grace = $gracePeriod ?? $this->gracePeriodFor($karyawan);
        $hoursPerDay = $jamKerjaPerHari ?? (int) Setting::getValue('payroll.jam_kerja_per_hari', 8);
        $hoursPerDay = max(1, $hoursPerDay);

        if ($menitTelat <= $grace) {
            return 0;
        }

        $hourlyRate = (int) floor($harianRate / $hoursPerDay);
        $billableMinutes = $menitTelat - $grace;
        $hours = (int) ceil($billableMinutes / 60);

        return $hours * $hourlyRate;
    }

    public function calculateUpahLembur(
        float $jamLembur,
        int $harianRate,
        ?int $jamKerjaPerHari = null,
        ?float $multiplier = null,
    ): int {
        if ($jamLembur <= 0 || $harianRate <= 0) {
            return 0;
        }

        $hoursPerDay = max(1, $jamKerjaPerHari ?? (int) Setting::getValue('payroll.jam_kerja_per_hari', 8));
        $multiplier = $multiplier ?? (float) Setting::getValue('payroll.lembur_multiplier', 1.5);
        $hourlyRate = $harianRate / $hoursPerDay;

        return (int) round($jamLembur * $hourlyRate * $multiplier);
    }

    /**
     * @return array{tahunan: int, sakit: int}
     */
    public function runningCutiTotals(Karyawan $karyawan, int $payrollYear, int $payrollMonth): array
    {
        return [
            'tahunan' => (int) $karyawan->gaji()
                ->where('tahun', $payrollYear)
                ->where('bulan', '<', $payrollMonth)
                ->sum('cuti_tahunan'),
            'sakit' => (int) $karyawan->gaji()
                ->where('tahun', $payrollYear)
                ->where('bulan', '<', $payrollMonth)
                ->sum('cuti_sakit'),
        ];
    }

    /**
     * @return array{tahunan: int, sakit: int, mendadak: int, izin: int}
     */
    public function cutiDaysForMonth(Karyawan $karyawan, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->toDateString();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $cutis = Cuti::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereDate('tgl_mulai', '<=', $monthEnd)
            ->whereDate('tgl_akhir', '>=', $monthStart)
            ->get();

        $totals = [
            'tahunan' => 0,
            'sakit' => 0,
            'mendadak' => 0,
            'izin' => 0,
        ];

        foreach ($cutis as $cuti) {
            $key = $cuti->typeKey();
            if (! array_key_exists($key, $totals)) {
                continue;
            }

            $totals[$key] += $cuti->daysInMonth($year, $month);
        }

        return $totals;
    }

    public function excessCutiDays(int $runningBefore, int $daysThisMonth, int $limit): int
    {
        if ($limit <= 0) {
            return $daysThisMonth;
        }

        $totalAfter = $runningBefore + $daysThisMonth;
        if ($totalAfter <= $limit) {
            return 0;
        }

        if ($runningBefore >= $limit) {
            return $daysThisMonth;
        }

        return $totalAfter - $limit;
    }
}
