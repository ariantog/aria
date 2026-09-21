<?php

use App\Models\Addrbook;
use App\Models\Cuti;
use App\Models\Karyawan;
use App\Models\KaryawanCutiSisa;
use App\Models\KaryawanCutiSisaLog;
use App\Models\User;
use App\Support\KaryawanVisibility;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();
    $this->superadmin = User::find(1);

    foreach ([
        'karyawan-list',
        'karyawan-create',
        'karyawan-edit',
        'karyawan-cuti-list',
        'karyawan-cuti-create',
        'karyawan-cuti-edit',
        'karyawan-cuti-delete',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->hr = User::factory()->create(['active' => true, 'name' => 'HR Editor']);
    $this->hr->givePermissionTo([
        'karyawan-list',
        'karyawan-create',
        'karyawan-edit',
        'karyawan-cuti-list',
        'karyawan-cuti-create',
        'karyawan-cuti-edit',
        'karyawan-cuti-delete',
    ]);

    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
});

function staffForSisa(array $overrides = []): Karyawan
{
    return Karyawan::create(array_merge([
        'nama' => 'Rina Sisa',
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

it('shows leftover tahunan and sakit on the employee list', function () {
    $karyawan = staffForSisa();

    $this->actingAs($this->hr)
        ->get(route('karyawan.index'))
        ->assertOk()
        ->assertSee('Cuti / Sisa', false)
        ->assertSee('data-testid="sisa-tahunan-'.$karyawan->id.'"', false)
        ->assertSee('data-testid="sisa-sakit-'.$karyawan->id.'"', false);
});

it('lets hr set leftover days and records who edited', function () {
    $karyawan = staffForSisa();

    $this->actingAs($this->hr)
        ->patch(route('karyawan.cuti-sisa.update', $karyawan), [
            'tahun' => 2026,
            'sisa_tahunan' => 4,
            'sisa_sakit' => 18,
            'catatan' => 'Sisa per 1 Agustus',
            'redirect' => 'show',
        ])
        ->assertRedirect(route('karyawan.show', $karyawan));

    $row = KaryawanCutiSisa::query()->where('karyawan_id', $karyawan->id)->where('tahun', 2026)->first();
    expect($row)->not->toBeNull()
        ->and($row->sisa_tahunan)->toBe(4)
        ->and($row->sisa_sakit)->toBe(18);

    $log = KaryawanCutiSisaLog::query()->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->hr->id)
        ->and($log->sumber)->toBe(KaryawanCutiSisaLog::SOURCE_MANUAL)
        ->and($log->sisa_tahunan_lama)->toBe(12)
        ->and($log->sisa_tahunan_baru)->toBe(4)
        ->and($log->catatan)->toBe('Sisa per 1 Agustus');

    $this->actingAs($this->hr)
        ->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('HR Editor', false)
        ->assertSee('Sisa per 1 Agustus', false)
        ->assertSee('12 → 4', false);
});

it('decrements leftover when tahunan cuti is recorded and restores on delete', function () {
    $karyawan = staffForSisa();

    $this->actingAs($this->hr)
        ->patch(route('karyawan.cuti-sisa.update', $karyawan), [
            'tahun' => now()->year,
            'sisa_tahunan' => 5,
            'sisa_sakit' => 20,
            'catatan' => 'Opening August',
        ]);

    $this->actingAs($this->hr)
        ->post(route('karyawan.cuti.store', $karyawan), [
            'tipe' => Cuti::TYPE_TAHUNAN,
            'tgl_mulai' => now()->toDateString(),
            'tgl_akhir' => now()->addDays(1)->toDateString(),
        ])
        ->assertRedirect();

    $row = KaryawanCutiSisa::query()->where('karyawan_id', $karyawan->id)->first();
    expect($row->sisa_tahunan)->toBe(3)
        ->and($row->sisa_sakit)->toBe(20);

    $cuti = Cuti::query()->first();
    $this->actingAs($this->hr)
        ->delete(route('cuti.destroy', $cuti))
        ->assertRedirect();

    expect(KaryawanCutiSisa::query()->where('karyawan_id', $karyawan->id)->value('sisa_tahunan'))->toBe(5);

    $cutiLogs = KaryawanCutiSisaLog::query()->where('sumber', KaryawanCutiSisaLog::SOURCE_CUTI)->get();
    expect($cutiLogs)->toHaveCount(2)
        ->and($cutiLogs->first()->user_id)->toBe($this->hr->id);
});

it('does not change leftover when recording izin', function () {
    $karyawan = staffForSisa();

    $this->actingAs($this->hr)
        ->patch(route('karyawan.cuti-sisa.update', $karyawan), [
            'tahun' => now()->year,
            'sisa_tahunan' => 6,
            'sisa_sakit' => 10,
        ]);

    $this->actingAs($this->hr)
        ->post(route('karyawan.cuti.store', $karyawan), [
            'tipe' => Cuti::TYPE_IZIN,
            'tgl_mulai' => now()->toDateString(),
            'tgl_akhir' => now()->toDateString(),
        ]);

    $row = KaryawanCutiSisa::query()->where('karyawan_id', $karyawan->id)->first();
    expect($row->sisa_tahunan)->toBe(6)
        ->and($row->sisa_sakit)->toBe(10);
});

it('saves leftover from the karyawan form', function () {
    $this->actingAs($this->hr)
        ->post(route('karyawan.store'), [
            'nama' => 'Budi Baru',
            'alamat' => 'Jl. Melati',
            'no_telp' => '0811111111',
            'bulanan' => 2_000_000,
            'harian' => 80_000,
            'bank_id' => $this->bank->id,
            'flag' => KaryawanVisibility::FLAG_PUBLIC,
            'sisa_tahunan' => 3,
            'sisa_sakit' => 12,
            'sisa_catatan' => 'Sisa awal',
        ])
        ->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::query()->where('nama', 'Budi Baru')->first();
    $row = KaryawanCutiSisa::query()->where('karyawan_id', $karyawan->id)->first();
    expect($row->sisa_tahunan)->toBe(3)
        ->and($row->sisa_sakit)->toBe(12);
});
