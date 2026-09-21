<?php

use App\Models\Addrbook;
use App\Models\Cuti;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\Payroll\GajiSalaryCalculator;
use App\Support\KaryawanVisibility;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();
    $this->superadmin = User::find(1);

    foreach ([
        'karyawan-list',
        'karyawan-create',
        'karyawan-cuti-list',
        'karyawan-cuti-create',
        'karyawan-cuti-edit',
        'karyawan-cuti-delete',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->hr = User::factory()->create(['active' => true]);
    $this->hr->givePermissionTo([
        'karyawan-list',
        'karyawan-create',
        'karyawan-cuti-list',
        'karyawan-cuti-create',
        'karyawan-cuti-edit',
        'karyawan-cuti-delete',
    ]);

    $this->viewer = User::factory()->create(['active' => true]);
    $this->viewer->givePermissionTo(['karyawan-cuti-list']);

    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
});

function makeStaff(array $overrides = []): Karyawan
{
    return Karyawan::create(array_merge([
        'nama' => 'Siti Karyawan',
        'nama_absensi' => 'SITI K',
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

it('stores nama absensi on karyawan create', function () {
    $this->actingAs($this->hr)
        ->post(route('karyawan.store'), [
            'nama' => 'Andi Fingerprint',
            'nama_absensi' => 'ANDI FP',
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

    $karyawan = Karyawan::query()->where('nama', 'Andi Fingerprint')->first();
    expect($karyawan)->not->toBeNull()
        ->and($karyawan->nama_absensi)->toBe('ANDI FP');
});

it('shows nama absensi on the create form and karyawan pages', function () {
    $karyawan = makeStaff();

    $this->actingAs($this->hr)
        ->get(route('karyawan.create'))
        ->assertOk()
        ->assertSee('Nama Absensi', false)
        ->assertSee('data-testid="nama-absensi"', false);

    $this->actingAs($this->hr)
        ->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('SITI K', false);
});

it('requires permission to view the cuti list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cuti.index'))
        ->assertForbidden();
});

it('creates tahunan and izin cuti records', function () {
    $karyawan = makeStaff();

    $this->actingAs($this->hr)
        ->post(route('karyawan.cuti.store', $karyawan), [
            'tipe' => Cuti::TYPE_TAHUNAN,
            'tgl_mulai' => '2026-09-01',
            'tgl_akhir' => '2026-09-03',
        ])
        ->assertRedirect(route('karyawan.show', $karyawan));

    $cuti = Cuti::query()->first();
    expect($cuti->tahunan)->toBe(3)
        ->and($cuti->izin)->toBe(0);

    $this->actingAs($this->hr)
        ->post(route('cuti.store'), [
            'karyawan_id' => $karyawan->id,
            'tipe' => Cuti::TYPE_IZIN,
            'tgl_mulai' => '2026-09-10',
            'tgl_akhir' => '2026-09-10',
        ])
        ->assertRedirect(route('karyawan.show', $karyawan));

    expect(Cuti::query()->where('tipe', Cuti::TYPE_IZIN)->value('izin'))->toBe(1);
});

it('updates and deletes cuti with the matching permissions', function () {
    $karyawan = makeStaff();
    $cuti = Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => Cuti::TYPE_SAKIT,
        'tgl_mulai' => '2026-09-01',
        'tgl_akhir' => '2026-09-01',
        'sakit' => 1,
    ]);

    $this->actingAs($this->viewer)
        ->put(route('cuti.update', $cuti), [
            'tipe' => Cuti::TYPE_MENDADAK,
            'tgl_mulai' => '2026-09-02',
            'tgl_akhir' => '2026-09-02',
        ])
        ->assertForbidden();

    $this->actingAs($this->hr)
        ->put(route('cuti.update', $cuti), [
            'tipe' => Cuti::TYPE_MENDADAK,
            'tgl_mulai' => '2026-09-02',
            'tgl_akhir' => '2026-09-03',
        ])
        ->assertRedirect(route('karyawan.show', $karyawan));

    $cuti->refresh();
    expect($cuti->tipe)->toBe(Cuti::TYPE_MENDADAK)
        ->and($cuti->mendadak)->toBe(2)
        ->and($cuti->sakit)->toBe(0);

    $this->actingAs($this->hr)
        ->delete(route('cuti.destroy', $cuti))
        ->assertRedirect(route('cuti.index'));

    expect(Cuti::query()->whereKey($cuti->id)->exists())->toBeFalse();
});

it('lists cuti and hides edit controls from view-only users', function () {
    $karyawan = makeStaff();
    Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => Cuti::TYPE_TAHUNAN,
        'tgl_mulai' => '2026-09-01',
        'tgl_akhir' => '2026-09-01',
        'tahunan' => 1,
    ]);

    $this->actingAs($this->viewer)
        ->get(route('cuti.index'))
        ->assertOk()
        ->assertSee('Daftar Cuti', false)
        ->assertSee('Siti Karyawan', false)
        ->assertDontSee('data-testid="cuti-create-link"', false);

    $this->actingAs($this->hr)
        ->get(route('cuti.index'))
        ->assertOk()
        ->assertSee('data-testid="cuti-create-link"', false);
});

it('allocates overlapping cuti days to each payroll month and counts izin', function () {
    $karyawan = makeStaff();

    Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => Cuti::TYPE_TAHUNAN,
        'tgl_mulai' => '2026-01-30',
        'tgl_akhir' => '2026-02-02',
        'tahunan' => 4,
    ]);

    Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => Cuti::TYPE_IZIN,
        'tgl_mulai' => '2026-02-10',
        'tgl_akhir' => '2026-02-11',
        'izin' => 2,
    ]);

    $calculator = app(GajiSalaryCalculator::class);
    $january = $calculator->calculate($karyawan, 1, 2026);
    $february = $calculator->calculate($karyawan, 2, 2026);

    expect($january['cuti_tahunan'])->toBe(2)
        ->and($january['hari_izin'])->toBe(0)
        ->and($february['cuti_tahunan'])->toBe(2)
        ->and($february['hari_izin'])->toBe(2)
        ->and($february['potongan_harian'])->toBe(200_000);
});
