<?php

use App\Models\AbsensiHari;
use App\Models\AbsensiImport;
use App\Models\Addrbook;
use App\Models\Cuti;
use App\Models\Gaji;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\Payroll\AbsensiImportService;
use App\Services\Payroll\GajiSalaryCalculator;
use App\Support\KaryawanVisibility;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();
    $this->superadmin = User::find(1);

    foreach ([
        'karyawan-list',
        'karyawan-create',
        'karyawan-gaji-list',
        'karyawan-gaji-create',
        'karyawan-absensi-list',
        'karyawan-absensi-import',
        'karyawan-hari-libur-list',
        'karyawan-hari-libur-create',
        'karyawan-hari-libur-delete',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->hr = User::factory()->create(['active' => true]);
    $this->hr->givePermissionTo([
        'karyawan-list',
        'karyawan-create',
        'karyawan-gaji-list',
        'karyawan-gaji-create',
        'karyawan-absensi-list',
        'karyawan-absensi-import',
        'karyawan-hari-libur-list',
        'karyawan-hari-libur-create',
        'karyawan-hari-libur-delete',
    ]);

    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
});

function staffWithAbsen(array $overrides = []): Karyawan
{
    return Karyawan::create(array_merge([
        'nama' => 'Sekar Test',
        'absen_id' => 'Core-010',
        'jam_kerja' => 8,
        'alamat' => 'Jl. Test',
        'no_telp' => '08123456789',
        'bulanan' => 3_000_000,
        'harian' => 100_000,
        'premi' => 0,
        'bank_id' => test()->bank->id,
        'flag' => KaryawanVisibility::FLAG_PUBLIC,
        'waktu_dibatasi' => true,
        'jam_masuk' => '08:00',
    ], $overrides));
}

function makeAbsensiUpload(array $employees, string $period = '2026-08-21 ~ 2026-08-28'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Lap. Log Absen');
    $sheet->setCellValue('A1', 'Lap. Detail Absensi');
    $sheet->setCellValue('C3', $period);
    foreach ([21, 22, 23, 24, 25, 26, 27, 28] as $index => $day) {
        $sheet->setCellValueByColumnAndRow($index + 1, 4, $day);
    }

    $row = 5;
    foreach ($employees as $employee) {
        $sheet->setCellValue('A'.$row, 'ID:');
        $sheet->setCellValue('C'.$row, $employee['id']);
        $sheet->setCellValue('K'.$row, $employee['nama'] ?? 'Tes');
        foreach (($employee['punches'] ?? []) as $col => $raw) {
            $sheet->setCellValueByColumnAndRow($col, $row + 1, $raw);
        }
        $row += 2;
    }

    $path = sys_get_temp_dir().'/absensi-upload-'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'absen-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('stores absen_id case-insensitively and jam_kerja on karyawan', function () {
    $this->actingAs($this->hr)
        ->post(route('karyawan.store'), [
            'nama' => 'Angga Fingerprint',
            'absen_id' => 'Core-002',
            'jam_kerja' => 7,
            'nama_absensi' => 'ANGGA',
            'alamat' => 'Jl. Melati',
            'no_telp' => '0811111111',
            'bulanan' => 2_000_000,
            'harian' => 80_000,
            'bank_id' => $this->bank->id,
            'flag' => KaryawanVisibility::FLAG_PUBLIC,
            'waktu_dibatasi' => 1,
            'jam_masuk' => '08:00',
        ])
        ->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::query()->where('nama', 'Angga Fingerprint')->first();
    expect($karyawan->absen_id)->toBe('Core-002')
        ->and($karyawan->jam_kerja)->toBe(7)
        ->and(Karyawan::findByAbsenId('core-002')?->id)->toBe($karyawan->id);
});

it('rejects a duplicate absen_id with different casing', function () {
    staffWithAbsen(['absen_id' => 'Core-010']);

    $this->actingAs($this->hr)
        ->post(route('karyawan.store'), [
            'nama' => 'Duplikat',
            'absen_id' => 'core-010',
            'jam_kerja' => 8,
            'alamat' => 'Jl. Melati',
            'no_telp' => '0811111111',
            'bulanan' => 2_000_000,
            'harian' => 80_000,
            'bank_id' => $this->bank->id,
            'flag' => KaryawanVisibility::FLAG_PUBLIC,
        ])
        ->assertSessionHasErrors('absen_id');
});

it('matches imported fingerprint ids case-insensitively', function () {
    $karyawan = staffWithAbsen(['absen_id' => 'core-010']);

    $this->actingAs($this->hr)
        ->post(route('absensi.store'), [
            'file' => makeAbsensiUpload([
                [
                    'id' => 'Core-010',
                    'nama' => 'Sekar',
                    'punches' => [
                        1 => '08:0016:00',
                        3 => '08:0012:00',
                    ],
                ],
                [
                    'id' => '99',
                    'nama' => 'Unknown',
                    'punches' => [1 => '08:0016:00'],
                ],
            ]),
        ])
        ->assertRedirect();

    $import = AbsensiImport::first();
    expect($import)->not->toBeNull()
        ->and($import->matched_count)->toBe(1)
        ->and($import->unmatched_count)->toBe(1);

    $friday = AbsensiHari::query()
        ->where('karyawan_id', $karyawan->id)
        ->whereDate('tanggal', '2026-08-21')
        ->first();

    expect($friday)->not->toBeNull()
        ->and($friday->masuk)->toBe('08:00')
        ->and($friday->pulang)->toBe('16:00')
        ->and($friday->jam)->toBe(8.0);

    $this->actingAs($this->hr)
        ->get(route('absensi.show', $import))
        ->assertOk()
        ->assertSee('Core-010', false)
        ->assertSee('99', false)
        ->assertSee('Belum terhubung', false);
});

it('treats sunday and holiday punches as extra hours that offset weekdays', function () {
    $karyawan = staffWithAbsen(['jam_kerja' => 8]);
    HariLibur::create(['tanggal' => '2026-08-25', 'nama' => 'Libur pabrik']);

    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        [
            'id' => 'Core-010',
            'nama' => 'Sekar',
            'punches' => [
                1 => '08:0016:00', // Fri 21 work 8h
                2 => '08:0016:00', // Sat 22 work 8h
                3 => '08:0016:00', // Sun 23 extra 8h
                // 24 (Mon) absent — sunday offsets this
                // 25 (Tue) holiday
                6 => '08:0016:00', // Wed 26
                7 => '08:0016:00', // Thu 27
                8 => '08:0016:00', // Fri 28
            ],
        ],
    ]));

    $summary = app(GajiSalaryCalculator::class)->attendanceSummary($karyawan, 2026, 8);

    // Imported dates 21–28. Work days among them: 21,22,24,26,27,28 (Sat counts; Sun + 25 holiday do not).
    expect($summary['hari_kerja'])->toBe(6)
        ->and($summary['jam_kerja_ekspektasi'])->toBe(48.0)
        ->and($summary['jam_kerja_aktual'])->toBe(48.0)
        ->and($summary['jam_lebih'])->toBe(0.0)
        ->and($summary['jam_kurang'])->toBe(0.0);
});

it('subtracts leave on work days from expected hours', function () {
    $karyawan = staffWithAbsen();
    HariLibur::create(['tanggal' => '2026-08-25', 'nama' => 'Libur pabrik']);
    Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => Cuti::TYPE_TAHUNAN,
        'tgl_mulai' => '2026-08-24',
        'tgl_akhir' => '2026-08-24',
        'tahunan' => 1,
    ]);

    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        [
            'id' => 'Core-010',
            'punches' => [
                1 => '08:0016:00',
                2 => '08:0016:00',
                6 => '08:0016:00',
                7 => '08:0016:00',
                8 => '08:0016:00',
            ],
        ],
    ]));

    $summary = app(GajiSalaryCalculator::class)->attendanceSummary($karyawan, 2026, 8);

    // Work days in file: 21,22,24,26,27,28 = 6; minus 24 leave => 5 * 8 = 40 expected; actual 40
    expect($summary['hari_cuti_kerja'])->toBe(1)
        ->and($summary['jam_kerja_ekspektasi'])->toBe(40.0)
        ->and($summary['jam_kerja_aktual'])->toBe(40.0);
});

it('prefills overtime on gaji create from surplus attendance hours', function () {
    $karyawan = staffWithAbsen(['jam_kerja' => 8, 'harian' => 100_000]);
    HariLibur::create(['tanggal' => '2026-08-25', 'nama' => 'Libur pabrik']);

    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        [
            'id' => 'Core-010',
            'punches' => [
                1 => '08:0018:00', // 10h Friday
                2 => '08:0016:00',
                4 => '08:0016:00',
                6 => '08:0016:00',
                7 => '08:0016:00',
                8 => '08:0016:00',
            ],
        ],
    ]));

    $this->actingAs($this->hr)
        ->get(route('karyawan.gaji.create', ['karyawan' => $karyawan->id, 'bulan' => 8, 'tahun' => 2026]))
        ->assertOk()
        ->assertSee('Jam kerja dari absensi', false)
        ->assertSee('name="jam_lembur"', false);

    $calculation = app(GajiSalaryCalculator::class)->calculate($karyawan, 8, 2026);
    expect($calculation['jam_kerja_aktual'])->toBe(50.0)
        ->and($calculation['jam_kerja_ekspektasi'])->toBe(48.0)
        ->and($calculation['jam_lembur'])->toBe(2.0);
});

it('saves attendance hours onto karyawan_gaji', function () {
    $karyawan = staffWithAbsen();
    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        [
            'id' => 'Core-010',
            'punches' => [1 => '08:0016:00', 2 => '08:0016:00'],
        ],
    ]));

    $this->actingAs($this->hr)
        ->post(route('karyawan.gaji.store', $karyawan), [
            'bulan' => 8,
            'tahun' => 2026,
            'bulanan' => 3_000_000,
            'harian_rate' => 100_000,
            'total_cuti_tahunan' => 0,
            'total_cuti_sakit' => 0,
            'total_cuti_mendadak' => 0,
            'hari_izin' => 0,
            'potong_harian' => 0,
            'menit_telat' => 0,
            'potong_telat' => 0,
            'jam_lembur' => 0,
            'upah_lembur' => 0,
            'bonus' => 0,
            'sanksi' => 0,
            'privasi' => KaryawanVisibility::FLAG_PUBLIC,
            'jam_kerja_aktual' => 16,
            'jam_kerja_ekspektasi' => 16,
        ])
        ->assertRedirect();

    $gaji = Gaji::first();
    expect($gaji->jam_kerja_aktual)->toBe(16.0)
        ->and($gaji->jam_kerja_ekspektasi)->toBe(16.0);
});

it('creates and deletes holidays from the SDM page', function () {
    $this->actingAs($this->hr)
        ->post(route('hari-libur.store'), [
            'tanggal' => '2026-08-17',
            'nama' => 'Hari Kemerdekaan',
            'catatan' => 'Nasional',
        ])
        ->assertRedirect();

    $libur = HariLibur::first();
    expect($libur->nama)->toBe('Hari Kemerdekaan');

    $this->actingAs($this->hr)
        ->get(route('hari-libur.index', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Hari Kemerdekaan', false);

    $this->actingAs($this->hr)
        ->delete(route('hari-libur.destroy', $libur))
        ->assertRedirect();

    expect(HariLibur::count())->toBe(0);
});

it('replaces the same absen_id dates on re-import', function () {
    $karyawan = staffWithAbsen();

    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        ['id' => 'Core-010', 'punches' => [1 => '08:0016:00']],
    ]));
    app(AbsensiImportService::class)->import(makeAbsensiUpload([
        ['id' => 'Core-010', 'punches' => [1 => '09:0017:00']],
    ]));

    $rows = AbsensiHari::query()->where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-21')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->masuk)->toBe('09:00');
});
