<?php

use App\Models\Addrbook;
use App\Models\Cuti;
use App\Models\Gaji;
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
        'karyawan-gaji-list',
        'karyawan-gaji-create',
        'karyawan-gaji-edit',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->payrollUser = User::factory()->create(['active' => true]);
    $this->payrollUser->givePermissionTo([
        'karyawan-list',
        'karyawan-gaji-list',
        'karyawan-gaji-create',
        'karyawan-gaji-edit',
    ]);

    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
});

function makeKaryawan(array $overrides = []): Karyawan
{
    return Karyawan::create(array_merge([
        'nama' => 'Budi Test',
        'alamat' => 'Jl. Test',
        'no_telp' => '08123456789',
        'bulanan' => 3_000_000,
        'harian' => 100_000,
        'premi' => 500_000,
        'bank_id' => test()->bank->id,
        'flag' => KaryawanVisibility::FLAG_PUBLIC,
    ], $overrides));
}

it('calculates salary with the 26-day formula and premi forfeiture', function () {
    $karyawan = makeKaryawan();
    $calculator = app(GajiSalaryCalculator::class);

    Cuti::create([
        'karyawan_id' => $karyawan->id,
        'tipe' => 3,
        'tgl_mulai' => now()->subMonth()->startOfMonth()->toDateString(),
        'tgl_akhir' => now()->subMonth()->startOfMonth()->toDateString(),
        'mendadak' => 1,
    ]);

    $result = $calculator->calculate($karyawan, now()->month, now()->year);

    expect($result['harian_total'])->toBe(2_600_000)
        ->and($result['cuti_mendadak'])->toBe(1)
        ->and($result['potongan_cuti_bulanan'])->toBe(100_000)
        ->and($result['potongan_cuti_premi'])->toBe(500_000)
        ->and($result['total_gaji'])->toBe(5_500_000);
});

it('hides private karyawan from payroll users', function () {
    $private = makeKaryawan(['nama' => 'Private Staff', 'flag' => KaryawanVisibility::FLAG_PRIVATE]);

    $this->actingAs($this->payrollUser)
        ->get(route('karyawan.show', $private))
        ->assertNotFound();

    $this->actingAs($this->superadmin)
        ->get(route('karyawan.show', $private))
        ->assertOk()
        ->assertSee('Private Staff', false);
});

it('creates editable payroll for a public karyawan', function () {
    $karyawan = makeKaryawan();

    $response = $this->actingAs($this->payrollUser)
        ->post(route('karyawan.gaji.store', $karyawan), [
            'bulan' => now()->month,
            'tahun' => now()->year,
            'bulanan' => 3_000_000,
            'harian_rate' => 100_000,
            'premi' => 500_000,
            'total_cuti_tahunan' => 0,
            'total_cuti_sakit' => 0,
            'total_cuti_mendadak' => 0,
            'potong_bulanan' => 0,
            'potong_premi' => 0,
            'bonus' => 100_000,
            'sanksi' => 50_000,
            'privasi' => KaryawanVisibility::FLAG_PUBLIC,
        ]);

    $response->assertRedirect(route('karyawan.show', $karyawan));

    $gaji = Gaji::first();
    expect($gaji)->not->toBeNull()
        ->and($gaji->total_gaji)->toBe(6_150_000)
        ->and($gaji->bonus)->toBe(100_000);
});

it('allows superadmin to store private payroll and blocks others from viewing it', function () {
    $karyawan = makeKaryawan();

    $this->actingAs($this->superadmin)
        ->post(route('karyawan.gaji.store', $karyawan), [
            'bulan' => now()->month,
            'tahun' => now()->year,
            'bulanan' => 3_000_000,
            'harian_rate' => 100_000,
            'premi' => 500_000,
            'total_cuti_tahunan' => 0,
            'total_cuti_sakit' => 0,
            'total_cuti_mendadak' => 0,
            'potong_bulanan' => 0,
            'potong_premi' => 0,
            'bonus' => 0,
            'sanksi' => 0,
            'privasi' => KaryawanVisibility::FLAG_PRIVATE,
        ])
        ->assertRedirect();

    $gaji = Gaji::first();

    $this->actingAs($this->payrollUser)
        ->get(route('gaji.edit', $gaji))
        ->assertNotFound();

    $this->actingAs($this->superadmin)
        ->get(route('gaji.edit', $gaji))
        ->assertOk();
});

it('updates payroll totals when dispute fields change', function () {
    $karyawan = makeKaryawan();
    $gaji = Gaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => now()->month,
        'tahun' => now()->year,
        'bulanan' => 3_000_000,
        'harian' => 2_600_000,
        'premi' => 500_000,
        'cuti_sakit' => 0,
        'cuti_tahunan' => 0,
        'cuti_mendadak' => 0,
        'total_cuti' => 0,
        'potongan_cuti_bulanan' => 0,
        'potongan_cuti_premi' => 0,
        'total_potongan' => 0,
        'bonus' => 0,
        'sanksi' => 0,
        'total_gaji' => 6_100_000,
        'bank_id' => $karyawan->bank_id,
        'flag' => KaryawanVisibility::FLAG_PUBLIC,
    ]);

    $this->actingAs($this->payrollUser)
        ->put(route('gaji.update', $gaji), [
            'bulan' => $gaji->bulan,
            'tahun' => $gaji->tahun,
            'bulanan' => 3_000_000,
            'harian_rate' => 100_000,
            'premi' => 500_000,
            'total_cuti_tahunan' => 0,
            'total_cuti_sakit' => 0,
            'total_cuti_mendadak' => 0,
            'potong_bulanan' => 0,
            'potong_premi' => 0,
            'bonus' => 0,
            'sanksi' => 200_000,
            'privasi' => KaryawanVisibility::FLAG_PUBLIC,
        ])
        ->assertRedirect(route('karyawan.show', $karyawan));

    expect($gaji->fresh()->total_gaji)->toBe(5_900_000);
});

it('renders payroll pages in the sidebar smoke paths', function () {
    $karyawan = makeKaryawan();

    $this->actingAs($this->superadmin)
        ->get(route('karyawan.index'))
        ->assertOk()
        ->assertSee('Karyawan List', false);

    $this->actingAs($this->superadmin)
        ->get(route('gaji.index'))
        ->assertOk()
        ->assertSee('Monthly Salary Management', false);

    $this->actingAs($this->superadmin)
        ->get(route('karyawan.gaji.create', $karyawan))
        ->assertOk()
        ->assertSee('Bikin Gaji', false);
});
