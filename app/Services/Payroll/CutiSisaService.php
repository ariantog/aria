<?php

namespace App\Services\Payroll;

use App\Models\Cuti;
use App\Models\Karyawan;
use App\Models\KaryawanCutiSisa;
use App\Models\KaryawanCutiSisaLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CutiSisaService
{
    /**
     * @return array{tahunan: int, sakit: int}
     */
    public function defaultLimits(): array
    {
        return [
            'tahunan' => max(0, (int) Setting::getValue('batas_cuti_tahunan', 12)),
            'sakit' => max(0, (int) Setting::getValue('batas_cuti_sakit', 30)),
        ];
    }

    /**
     * @return array{sisa_tahunan: int, sisa_sakit: int, exists: bool, tahun: int}
     */
    public function leftover(Karyawan $karyawan, int $year): array
    {
        $defaults = $this->defaultLimits();
        $row = KaryawanCutiSisa::query()
            ->where('karyawan_id', $karyawan->id)
            ->where('tahun', $year)
            ->first();

        return [
            'sisa_tahunan' => $row ? (int) $row->sisa_tahunan : $defaults['tahunan'],
            'sisa_sakit' => $row ? (int) $row->sisa_sakit : $defaults['sakit'],
            'exists' => $row !== null,
            'tahun' => $year,
        ];
    }

    /**
     * @param  list<int>  $karyawanIds
     * @return Collection<int, array{sisa_tahunan: int, sisa_sakit: int, exists: bool, tahun: int}>
     */
    public function leftoverMap(array $karyawanIds, int $year): Collection
    {
        if ($karyawanIds === []) {
            return collect();
        }

        $defaults = $this->defaultLimits();
        $rows = KaryawanCutiSisa::query()
            ->whereIn('karyawan_id', $karyawanIds)
            ->where('tahun', $year)
            ->get()
            ->keyBy('karyawan_id');

        return collect($karyawanIds)->mapWithKeys(function (int $id) use ($rows, $defaults, $year) {
            $row = $rows->get($id);

            return [$id => [
                'sisa_tahunan' => $row ? (int) $row->sisa_tahunan : $defaults['tahunan'],
                'sisa_sakit' => $row ? (int) $row->sisa_sakit : $defaults['sakit'],
                'exists' => $row !== null,
                'tahun' => $year,
            ]];
        });
    }

    public function setManual(
        Karyawan $karyawan,
        int $year,
        int $sisaTahunan,
        int $sisaSakit,
        ?User $user,
        ?string $catatan = null,
    ): KaryawanCutiSisa {
        return $this->write(
            $karyawan,
            $year,
            $sisaTahunan,
            $sisaSakit,
            $user,
            KaryawanCutiSisaLog::SOURCE_MANUAL,
            $catatan,
        );
    }

    public function applyCutiChange(Karyawan $karyawan, ?Cuti $before, ?Cuti $after, ?User $user): void
    {
        $beforeDays = $this->quotaDaysByYear($before);
        $afterDays = $this->quotaDaysByYear($after);
        $years = array_unique(array_merge(array_keys($beforeDays), array_keys($afterDays)));

        foreach ($years as $year) {
            $deltaTahunan = ($afterDays[$year]['tahunan'] ?? 0) - ($beforeDays[$year]['tahunan'] ?? 0);
            $deltaSakit = ($afterDays[$year]['sakit'] ?? 0) - ($beforeDays[$year]['sakit'] ?? 0);

            if ($deltaTahunan === 0 && $deltaSakit === 0) {
                continue;
            }

            $current = $this->leftover($karyawan, $year);
            $note = $this->cutiChangeNote($before, $after);

            $this->write(
                $karyawan,
                $year,
                $current['sisa_tahunan'] - $deltaTahunan,
                $current['sisa_sakit'] - $deltaSakit,
                $user,
                KaryawanCutiSisaLog::SOURCE_CUTI,
                $note,
            );
        }
    }

    /**
     * @return array<int, array{tahunan: int, sakit: int}>
     */
    public function quotaDaysByYear(?Cuti $cuti): array
    {
        if (! $cuti || ! $cuti->tgl_mulai || ! $cuti->tgl_akhir) {
            return [];
        }

        $key = $cuti->typeKey();
        if (! in_array($key, ['tahunan', 'sakit'], true)) {
            return [];
        }

        $startYear = \Carbon\Carbon::parse($cuti->tgl_mulai)->year;
        $endYear = \Carbon\Carbon::parse($cuti->tgl_akhir)->year;
        $result = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $days = $cuti->daysInYear($year);
            if ($days <= 0) {
                continue;
            }

            $result[$year] = [
                'tahunan' => $key === 'tahunan' ? $days : 0,
                'sakit' => $key === 'sakit' ? $days : 0,
            ];
        }

        return $result;
    }

    private function write(
        Karyawan $karyawan,
        int $year,
        int $sisaTahunan,
        int $sisaSakit,
        ?User $user,
        string $source,
        ?string $catatan,
    ): KaryawanCutiSisa {
        $sisaTahunan = max(0, $sisaTahunan);
        $sisaSakit = max(0, $sisaSakit);

        return DB::transaction(function () use ($karyawan, $year, $sisaTahunan, $sisaSakit, $user, $source, $catatan) {
            $defaults = $this->defaultLimits();
            $row = KaryawanCutiSisa::query()->firstOrNew([
                'karyawan_id' => $karyawan->id,
                'tahun' => $year,
            ]);

            $oldTahunan = $row->exists ? (int) $row->sisa_tahunan : $defaults['tahunan'];
            $oldSakit = $row->exists ? (int) $row->sisa_sakit : $defaults['sakit'];

            if ($oldTahunan === $sisaTahunan && $oldSakit === $sisaSakit && $row->exists) {
                return $row;
            }

            $row->sisa_tahunan = $sisaTahunan;
            $row->sisa_sakit = $sisaSakit;
            $row->save();

            KaryawanCutiSisaLog::query()->create([
                'karyawan_id' => $karyawan->id,
                'user_id' => $user?->id,
                'tahun' => $year,
                'sumber' => $source,
                'sisa_tahunan_lama' => $oldTahunan,
                'sisa_tahunan_baru' => $sisaTahunan,
                'sisa_sakit_lama' => $oldSakit,
                'sisa_sakit_baru' => $sisaSakit,
                'catatan' => $catatan,
            ]);

            return $row;
        });
    }

    private function cutiChangeNote(?Cuti $before, ?Cuti $after): string
    {
        if ($before && $after) {
            return 'Ubah cuti '.$after->type_name;
        }

        if ($after) {
            return 'Tambah cuti '.$after->type_name;
        }

        if ($before) {
            return 'Hapus cuti '.$before->type_name;
        }

        return 'Perubahan cuti';
    }
}
