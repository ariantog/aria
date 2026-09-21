<?php

use App\Services\Payroll\AbsensiLogParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function writeAbsensiFixture(array $employees, string $period = '2026-08-21 ~ 2026-08-28', array $days = [21, 22, 23, 24, 25, 26, 27, 28]): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Lap. Log Absen');
    $sheet->setCellValue('A1', 'Lap. Detail Absensi');
    $sheet->setCellValue('C3', $period);

    foreach ($days as $index => $day) {
        $sheet->setCellValueByColumnAndRow($index + 1, 4, $day);
    }

    $row = 5;
    foreach ($employees as $employee) {
        $sheet->setCellValue('A'.$row, 'ID:');
        $sheet->setCellValue('C'.$row, $employee['id']);
        $sheet->setCellValue('I'.$row, 'Nama:');
        $sheet->setCellValue('K'.$row, $employee['nama'] ?? 'Tes');
        $sheet->setCellValue('S'.$row, 'Dept.:');
        $sheet->setCellValue('U'.$row, $employee['dept'] ?? 'Admin');
        $punches = $employee['punches'] ?? [];
        foreach ($punches as $col => $raw) {
            $sheet->setCellValueByColumnAndRow($col, $row + 1, $raw);
        }
        $row += 2;
    }

    $path = sys_get_temp_dir().'/absensi-fixture-'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('splits concatenated punch times and computes hours from first to last', function () {
    $parser = new AbsensiLogParser;

    expect($parser->extractPunches('08:2416:08'))->toBe(['08:24', '16:08'])
        ->and($parser->extractPunches('07:2307:2316:08'))->toBe(['07:23', '07:23', '16:08'])
        ->and($parser->extractPunches('08:18'))->toBe(['08:18']);

    expect($parser->hoursFromPunches(['08:24', '16:08']))
        ->toMatchArray(['masuk' => '08:24', 'pulang' => '16:08', 'jam' => 7.73, 'incomplete' => false]);

    expect($parser->hoursFromPunches(['08:18']))
        ->toMatchArray(['masuk' => '08:18', 'pulang' => null, 'jam' => 0.0, 'incomplete' => true]);

    expect($parser->hoursFromPunches(['22:00', '06:00']))
        ->toMatchArray(['masuk' => '22:00', 'pulang' => '06:00', 'jam' => 8.0, 'incomplete' => false]);

    expect($parser->hoursFromPunches([]))
        ->toMatchArray(['masuk' => null, 'pulang' => null, 'jam' => 0.0, 'incomplete' => false]);
});

it('parses the Lap. Log Absen layout', function () {
    $path = writeAbsensiFixture([
        [
            'id' => 'Core-010',
            'nama' => 'Sekar',
            'punches' => [
                1 => '08:0016:00',
                3 => '08:0012:09',
            ],
        ],
        [
            'id' => 'core-002',
            'nama' => 'Angga',
            'punches' => [
                1 => '08:2416:08',
                8 => '08:18',
            ],
        ],
    ]);

    $parsed = (new AbsensiLogParser)->parse($path);
    unlink($path);

    expect($parsed['period_start'])->toBe('2026-08-21')
        ->and($parsed['period_end'])->toBe('2026-08-28')
        ->and($parsed['dates'])->toBe([
            '2026-08-21',
            '2026-08-22',
            '2026-08-23',
            '2026-08-24',
            '2026-08-25',
            '2026-08-26',
            '2026-08-27',
            '2026-08-28',
        ])
        ->and($parsed['employees'])->toHaveCount(2);

    $sekar = $parsed['employees'][0];
    $sunday = collect($sekar['days'])->firstWhere('tanggal', '2026-08-23');

    expect($sekar['absen_id'])->toBe('Core-010')
        ->and($sunday['masuk'])->toBe('08:00')
        ->and($sunday['pulang'])->toBe('12:09')
        ->and($sunday['jam'])->toBe(4.15);

    $anggaFriday = collect($parsed['employees'][1]['days'])->firstWhere('tanggal', '2026-08-28');
    expect($anggaFriday['incomplete'])->toBeTrue()
        ->and($anggaFriday['jam'])->toBe(0.0);
});

it('parses the uploaded fingerprint workbook', function () {
    $path = dirname(__DIR__, 2).'/old/absen 28-08-2026.xls';
    expect(is_file($path))->toBeTrue();

    $parsed = (new AbsensiLogParser)->parse($path);

    expect($parsed['period_start'])->toBe('2026-08-21')
        ->and($parsed['period_end'])->toBe('2026-08-28')
        ->and($parsed['employees'])->toHaveCount(83);

    $sekar = collect($parsed['employees'])->firstWhere('absen_id', 'Core-010');
    $sunday = collect($sekar['days'])->firstWhere('tanggal', '2026-08-23');

    expect($sekar['nama'])->toBe('Sekar')
        ->and($sunday['punches'])->toBe(['08:00', '12:09'])
        ->and($sunday['jam'])->toBe(4.15);

    $numeric = collect($parsed['employees'])->firstWhere('absen_id', '76');
    expect($numeric['nama'])->toBe('Olivia');
});
