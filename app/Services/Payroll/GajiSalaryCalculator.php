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
     *   premi: int,
     *   cuti_tahunan: int,
     *   cuti_sakit: int,
     *   cuti_mendadak: int,
     *   total_cuti: int,
     *   denda_cuti_tahunan: int,
     *   denda_cuti_sakit: int,
     *   potongan_cuti_bulanan: int,
     *   potongan_cuti_premi: int,
     *   total_potongan: int,
     *   bonus: int,
     *   sanksi: int,
     *   total_gaji: int,
     *   limit_tahunan: int,
     *   limit_sakit: int,
     *   running_tahunan_before: int,
     *   running_sakit_before: int,
     * }
     */
    public function calculate(
        Karyawan $karyawan,
        int $bulan,
        int $tahun,
        int $bonus = 0,
        int $sanksi = 0,
        ?int $overridePotongBulanan = null,
        ?int $overridePotongPremi = null,
        ?int $overrideCutiTahunan = null,
        ?int $overrideCutiSakit = null,
        ?int $overrideCutiMendadak = null,
    ): array {
        $limitTahunan = (int) Setting::getValue('batas_cuti_tahunan', 12);
        $limitSakit = (int) Setting::getValue('batas_cuti_sakit', 30);

        $payPeriod = Carbon::create($tahun, $bulan, 1);
        $cutiPeriod = $payPeriod->copy()->subMonth();

        $running = $this->runningCutiTotals($karyawan, $tahun, $bulan);

        $cutiCounts = $this->cutiDaysForMonth(
            $karyawan,
            $cutiPeriod->year,
            $cutiPeriod->month,
        );

        if ($overrideCutiTahunan !== null) {
            $cutiCounts['tahunan'] = max(0, $overrideCutiTahunan);
        }
        if ($overrideCutiSakit !== null) {
            $cutiCounts['sakit'] = max(0, $overrideCutiSakit);
        }
        if ($overrideCutiMendadak !== null) {
            $cutiCounts['mendadak'] = max(0, $overrideCutiMendadak);
        }

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
        $defaultPotongBulanan = ($dendaTahunan + $dendaSakit + $cutiCounts['mendadak']) * $harianRate;
        $potongBulanan = $overridePotongBulanan ?? $defaultPotongBulanan;

        $totalCuti = $cutiCounts['tahunan'] + $cutiCounts['sakit'] + $cutiCounts['mendadak'];
        $defaultPotongPremi = $totalCuti > 0 ? (int) $karyawan->premi : 0;
        $potongPremi = $overridePotongPremi ?? $defaultPotongPremi;

        $bulanan = (int) $karyawan->bulanan;
        $premi = (int) $karyawan->premi;
        $harianTotal = $harianRate * self::WORKING_DAYS_PER_MONTH;
        $totalPotongan = $potongBulanan + $potongPremi;
        $totalGaji = $bulanan + $harianTotal + $premi + $bonus - $sanksi - $totalPotongan;

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanan' => $bulanan,
            'harian_rate' => $harianRate,
            'harian_total' => $harianTotal,
            'premi' => $premi,
            'cuti_tahunan' => $cutiCounts['tahunan'],
            'cuti_sakit' => $cutiCounts['sakit'],
            'cuti_mendadak' => $cutiCounts['mendadak'],
            'total_cuti' => $totalCuti,
            'denda_cuti_tahunan' => $dendaTahunan,
            'denda_cuti_sakit' => $dendaSakit,
            'potongan_cuti_bulanan' => $potongBulanan,
            'potongan_cuti_premi' => $potongPremi,
            'total_potongan' => $totalPotongan,
            'bonus' => $bonus,
            'sanksi' => $sanksi,
            'total_gaji' => $totalGaji,
            'limit_tahunan' => $limitTahunan,
            'limit_sakit' => $limitSakit,
            'running_tahunan_before' => $running['tahunan'],
            'running_sakit_before' => $running['sakit'],
            'cuti_period_month' => $cutiPeriod->month,
            'cuti_period_year' => $cutiPeriod->year,
        ];
    }

    /**
     * Sum cuti days already paid out on earlier payroll slips in the same calendar year.
     *
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
     * @return array{tahunan: int, sakit: int, mendadak: int}
     */
    public function cutiDaysForMonth(Karyawan $karyawan, int $year, int $month): array
    {
        $totals = Cuti::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereYear('tgl_mulai', $year)
            ->whereMonth('tgl_mulai', $month)
            ->selectRaw('COALESCE(SUM(tahunan), 0) as total_tahunan, COALESCE(SUM(sakit), 0) as total_sakit, COALESCE(SUM(mendadak), 0) as total_mendadak')
            ->first();

        return [
            'tahunan' => (int) ($totals->total_tahunan ?? 0),
            'sakit' => (int) ($totals->total_sakit ?? 0),
            'mendadak' => (int) ($totals->total_mendadak ?? 0),
        ];
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
