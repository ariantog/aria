<?php

namespace App\Services\Payroll;

use App\Models\AbsensiHari;
use App\Models\AbsensiImport;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AbsensiImportService
{
    public function __construct(
        protected AbsensiLogParser $parser,
    ) {}

    /**
     * @return array{import: AbsensiImport, parsed: array<string, mixed>}
     */
    public function import(UploadedFile|string $file, ?User $user = null): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $filename = $file instanceof UploadedFile
            ? (string) $file->getClientOriginalName()
            : basename((string) $file);

        $parsed = $this->parser->parse((string) $path);
        $lookup = $this->absenLookup();

        $import = DB::transaction(function () use ($parsed, $filename, $user, $lookup) {
            $dates = $parsed['dates'];
            $absenKeys = [];
            foreach ($parsed['employees'] as $employee) {
                $absenKeys[mb_strtolower(trim((string) $employee['absen_id']))] = true;
            }

            $this->replaceExistingDays(array_keys($absenKeys), $dates);

            $import = AbsensiImport::query()->create([
                'filename' => $filename,
                'period_start' => $parsed['period_start'],
                'period_end' => $parsed['period_end'],
                'user_id' => $user?->id,
                'karyawan_count' => count($parsed['employees']),
                'matched_count' => 0,
                'unmatched_count' => 0,
                'day_count' => 0,
            ]);

            $matched = [];
            $unmatched = [];
            $dayCount = 0;

            foreach ($parsed['employees'] as $employee) {
                $absenId = trim((string) $employee['absen_id']);
                $key = mb_strtolower($absenId);
                $karyawan = $lookup[$key] ?? null;

                if ($karyawan) {
                    $matched[$key] = $absenId;
                } else {
                    $unmatched[$key] = $absenId;
                }

                foreach ($employee['days'] as $day) {
                    AbsensiHari::query()->create([
                        'import_id' => $import->id,
                        'karyawan_id' => $karyawan?->id,
                        'absen_id' => $absenId,
                        'nama_mesin' => $employee['nama'] !== '' ? $employee['nama'] : null,
                        'tanggal' => $day['tanggal'],
                        'masuk' => $day['masuk'],
                        'pulang' => $day['pulang'],
                        'jam' => $day['jam'],
                        'punches_raw' => $day['punches_raw'] !== '' ? $day['punches_raw'] : null,
                        'incomplete' => $day['incomplete'],
                    ]);
                    $dayCount++;
                }
            }

            $import->update([
                'matched_count' => count($matched),
                'unmatched_count' => count($unmatched),
                'day_count' => $dayCount,
            ]);

            return $import->fresh();
        });

        return [
            'import' => $import,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array<string, Karyawan>
     */
    public function absenLookup(): array
    {
        $map = [];

        foreach (Karyawan::query()->whereNotNull('absen_id')->get(['id', 'absen_id', 'nama']) as $karyawan) {
            $key = mb_strtolower(trim((string) $karyawan->absen_id));
            if ($key !== '') {
                $map[$key] = $karyawan;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $absenKeys  lowercase absen ids
     * @param  list<string>  $dates
     */
    protected function replaceExistingDays(array $absenKeys, array $dates): void
    {
        if ($absenKeys === [] || $dates === []) {
            return;
        }

        $existing = AbsensiHari::query()
            ->whereIn('tanggal', $dates)
            ->get(['id', 'absen_id']);

        $ids = $existing
            ->filter(fn (AbsensiHari $row) => in_array(mb_strtolower(trim((string) $row->absen_id)), $absenKeys, true))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            AbsensiHari::query()->whereIn('id', $ids)->delete();
        }
    }
}
