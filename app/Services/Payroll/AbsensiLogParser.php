<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class AbsensiLogParser
{
    public const SHEET_HINT = 'Log Absen';

    /**
     * Parse only the "Lap. Log Absen" sheet from a fingerprint export.
     *
     * @return array{
     *   period_start: string,
     *   period_end: string,
     *   dates: list<string>,
     *   employees: list<array{
     *     absen_id: string,
     *     nama: string,
     *     dept: string,
     *     days: list<array{
     *       tanggal: string,
     *       punches: list<string>,
     *       masuk: ?string,
     *       pulang: ?string,
     *       jam: float,
     *       incomplete: bool,
     *       punches_raw: string
     *     }>
     *   }>
     * }
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File absensi tidak dapat dibaca.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $this->findLogSheet($spreadsheet);

        [$periodStart, $periodEnd] = $this->parsePeriod($sheet);
        $dates = $this->parseDateHeaders($sheet, $periodStart, $periodEnd);

        if ($dates === []) {
            throw new RuntimeException('Baris tanggal pada sheet Lap. Log Absen tidak ditemukan.');
        }

        $employees = $this->parseEmployees($sheet, $dates);

        if ($employees === []) {
            throw new RuntimeException('Tidak ada baris ID karyawan pada sheet Lap. Log Absen.');
        }

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'dates' => array_values($dates),
            'employees' => $employees,
        ];
    }

    /**
     * Split concatenated fingerprint punches such as "08:2416:08" into HH:MM times.
     *
     * @return list<string>
     */
    public function extractPunches(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        preg_match_all('/\d{1,2}:\d{2}/', $raw, $matches);

        return array_values(array_map(
            fn (string $time) => $this->normalizeTime($time),
            $matches[0] ?? [],
        ));
    }

    /**
     * Worked hours = last punch − first punch. A single punch is incomplete (0 hours).
     *
     * @param  list<string>  $punches
     * @return array{masuk: ?string, pulang: ?string, jam: float, incomplete: bool}
     */
    public function hoursFromPunches(array $punches): array
    {
        $punches = array_values(array_filter($punches, fn ($time) => is_string($time) && $time !== ''));

        if (count($punches) < 2) {
            return [
                'masuk' => $punches[0] ?? null,
                'pulang' => null,
                'jam' => 0.0,
                'incomplete' => $punches !== [],
            ];
        }

        $masuk = $punches[0];
        $pulang = $punches[array_key_last($punches)];
        $start = $this->minutesOf($masuk);
        $end = $this->minutesOf($pulang);

        if ($start === null || $end === null) {
            return [
                'masuk' => $masuk,
                'pulang' => $pulang,
                'jam' => 0.0,
                'incomplete' => true,
            ];
        }

        $delta = $end - $start;
        if ($delta < 0) {
            $delta += 24 * 60;
        }

        return [
            'masuk' => $masuk,
            'pulang' => $pulang,
            'jam' => round($delta / 60, 2),
            'incomplete' => false,
        ];
    }

    private function findLogSheet($spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if (stripos($worksheet->getTitle(), self::SHEET_HINT) !== false) {
                return $worksheet;
            }
        }

        throw new RuntimeException('Sheet "Lap. Log Absen" tidak ditemukan di file ini.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parsePeriod(Worksheet $sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= 6; $row++) {
            for ($col = 1; $col <= $highestColumn; $col++) {
                $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row)->getValue());
                if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
                    return [
                        Carbon::parse($matches[1])->startOfDay(),
                        Carbon::parse($matches[2])->startOfDay(),
                    ];
                }
            }
        }

        throw new RuntimeException('Periode absensi (YYYY-MM-DD ~ YYYY-MM-DD) tidak ditemukan.');
    }

    /**
     * @return array<int, string> column index => Y-m-d
     */
    private function parseDateHeaders(Worksheet $sheet, Carbon $periodStart, Carbon $periodEnd): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerRow = 4;
        $dates = [];

        for ($col = 1; $col <= $highestColumn; $col++) {
            $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col).$headerRow)->getValue());
            if (! preg_match('/^\d{1,2}$/', $value)) {
                continue;
            }

            $day = (int) $value;
            $date = $this->resolveHeaderDate($day, $periodStart, $periodEnd);
            if ($date) {
                $dates[$col] = $date->toDateString();
            }
        }

        return $dates;
    }

    private function resolveHeaderDate(int $day, Carbon $periodStart, Carbon $periodEnd): ?Carbon
    {
        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd)) {
            if ($cursor->day === $day) {
                return $cursor->copy();
            }
            $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array<int, string>  $dates
     * @return list<array<string, mixed>>
     */
    private function parseEmployees(Worksheet $sheet, array $dates): array
    {
        $employees = [];
        $highestRow = $sheet->getHighestRow();

        for ($row = 5; $row <= $highestRow; $row++) {
            $label = trim((string) $sheet->getCell('A'.$row)->getValue());
            if (! preg_match('/^ID\s*:?\s*$/i', $label)) {
                continue;
            }

            $absenId = trim((string) $sheet->getCell('C'.$row)->getValue());
            if ($absenId === '') {
                continue;
            }

            $nama = trim((string) $sheet->getCell('K'.$row)->getValue());
            $dept = trim((string) $sheet->getCell('U'.$row)->getValue());
            $punchRow = $row + 1;

            $days = [];
            foreach ($dates as $col => $tanggal) {
                $raw = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col).$punchRow)->getValue());
                $punches = $this->extractPunches($raw);
                $hours = $this->hoursFromPunches($punches);

                $days[] = [
                    'tanggal' => $tanggal,
                    'punches' => $punches,
                    'masuk' => $hours['masuk'],
                    'pulang' => $hours['pulang'],
                    'jam' => $hours['jam'],
                    'incomplete' => $hours['incomplete'],
                    'punches_raw' => $raw,
                ];
            }

            $employees[] = [
                'absen_id' => $absenId,
                'nama' => $nama,
                'dept' => $dept,
                'days' => $days,
            ];
        }

        return $employees;
    }

    private function normalizeTime(string $time): string
    {
        [$hour, $minute] = array_pad(explode(':', $time, 2), 2, '0');

        return sprintf('%02d:%02d', (int) $hour, (int) $minute);
    }

    private function minutesOf(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}
