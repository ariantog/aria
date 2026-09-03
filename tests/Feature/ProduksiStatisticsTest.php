<?php

use App\Models\Produksi;
use App\Models\Report;
use App\Models\User;
use App\Models\Worker;
use App\Services\Produksi\ProduksiStatisticsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'superadmin']);
    $this->user->assignRole($role);
});

it('aggregates potong statistics by worker and month', function () {
    $potongA = Worker::create(['name' => 'Potong A', 'type' => Worker::TYPE_POTONG]);
    $potongB = Worker::create(['name' => 'Potong B', 'type' => Worker::TYPE_POTONG]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'Shirt A',
        'quantity' => 10,
        'potong_id' => $potongA->id,
        'potong_date' => "{$year}-03-05",
        'surat_jalan_potong' => 'SJP-1',
    ]);
    Produksi::create([
        'temp_name' => 'Shirt B',
        'quantity' => 20,
        'potong_id' => $potongA->id,
        'potong_date' => "{$year}-03-12",
        'surat_jalan_potong' => 'SJP-2',
    ]);
    Produksi::create([
        'temp_name' => 'Pants C',
        'quantity' => 15,
        'potong_id' => $potongB->id,
        'potong_date' => "{$year}-04-01",
    ]);

    $stats = app(ProduksiStatisticsService::class);
    [$start, $end] = $stats->resolveDateRange(3, $year);

    $summary = $stats->potongWorkerSummary($start, $end);

    expect($summary)->toHaveCount(1);
    expect($summary->first()->worker_name)->toBe('Potong A');
    expect($summary->first()->kitir_count)->toBe(2);
    expect($summary->first()->total_qty)->toBe(30);
    expect($summary->first()->sjp_count)->toBe(2);

    $monthly = $stats->potongMonthlyTotals($year);
    expect($monthly->firstWhere('month', 3)?->total_qty)->toBe(30);
    expect($monthly->firstWhere('month', 4)?->total_qty)->toBe(15);
});

it('aggregates qc statistics with lag metrics', function () {
    $qc = Worker::create(['name' => 'QC One', 'type' => Worker::TYPE_QC]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'QC Item',
        'quantity' => 8,
        'qc_id' => $qc->id,
        'qc_date' => "{$year}-06-10",
        'potong_date' => "{$year}-06-01",
        'setor_date' => "{$year}-06-08",
        'status' => Produksi::STATUS_SETOR,
    ]);

    $stats = app(ProduksiStatisticsService::class);
    [$start, $end] = $stats->resolveDateRange(6, $year);

    $summary = $stats->qcWorkerSummary($start, $end);

    expect($summary)->toHaveCount(1);
    expect($summary->first()->worker_name)->toBe('QC One');
    expect($summary->first()->total_qty)->toBe(8);
    expect($summary->first()->avg_potong_lag_days)->toBe(9.0);
    expect($summary->first()->avg_setor_lag_days)->toBe(2.0);
});

it('aggregates jahit statistics by worker and month', function () {
    $jahit = Worker::create(['name' => 'Jahit One', 'type' => Worker::TYPE_JAHIT]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'Jahit Shirt',
        'quantity' => 12,
        'jahit_id' => $jahit->id,
        'jahit_date' => "{$year}-05-10",
        'potong_date' => "{$year}-05-01",
        'surat_jalan_potong' => 'SJP-J1',
    ]);
    Produksi::create([
        'temp_name' => 'Jahit Pants',
        'quantity' => 6,
        'jahit_id' => $jahit->id,
        'jahit_date' => "{$year}-05-20",
        'potong_date' => "{$year}-05-18",
        'surat_jalan_potong' => 'SJP-J2',
    ]);

    $stats = app(ProduksiStatisticsService::class);
    [$start, $end] = $stats->resolveDateRange(5, $year);

    $summary = $stats->jahitWorkerSummary($start, $end);

    expect($summary)->toHaveCount(1);
    expect($summary->first()->worker_name)->toBe('Jahit One');
    expect($summary->first()->kitir_count)->toBe(2);
    expect($summary->first()->total_qty)->toBe(18);
    expect($summary->first()->sjp_count)->toBe(2);
    expect($summary->first()->avg_potong_lag_days)->toBe(5.5);

    $monthly = $stats->jahitMonthlyTotals($year);
    expect($monthly->firstWhere('month', 5)?->total_qty)->toBe(18);
});

it('aggregates pritil statistics with lag metrics', function () {
    $pritil = Worker::create(['name' => 'Pritil One', 'type' => Worker::TYPE_PRITIL]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'Pritil Item',
        'quantity' => 4,
        'pritil_id' => $pritil->id,
        'pritil_date' => "{$year}-07-12",
        'potong_date' => "{$year}-07-01",
        'setor_date' => "{$year}-07-10",
        'status' => Produksi::STATUS_SETOR,
    ]);

    $stats = app(ProduksiStatisticsService::class);
    [$start, $end] = $stats->resolveDateRange(7, $year);

    $summary = $stats->pritilWorkerSummary($start, $end);

    expect($summary)->toHaveCount(1);
    expect($summary->first()->worker_name)->toBe('Pritil One');
    expect($summary->first()->total_qty)->toBe(4);
    expect($summary->first()->avg_potong_lag_days)->toBe(11.0);
    expect($summary->first()->avg_setor_lag_days)->toBe(2.0);
});

it('filters produksi statistics by date range and status including zero', function () {
    $potong = Worker::create(['name' => 'Range Cutter', 'type' => Worker::TYPE_POTONG]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'In-range Produksi',
        'quantity' => 1234,
        'potong_id' => $potong->id,
        'potong_date' => "{$year}-03-10",
        'status' => Produksi::STATUS_PRODUKSI,
    ]);
    Produksi::create([
        'temp_name' => 'In-range Setor',
        'quantity' => 77,
        'potong_id' => $potong->id,
        'potong_date' => "{$year}-03-11",
        'status' => Produksi::STATUS_SETOR,
    ]);
    Produksi::create([
        'temp_name' => 'Out-of-range Produksi',
        'quantity' => 8888,
        'potong_id' => $potong->id,
        'potong_date' => "{$year}-05-01",
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $stats = app(ProduksiStatisticsService::class);
    $ctx = $stats->reportContext([
        'from' => "{$year}-03-01",
        'to' => "{$year}-03-31",
        'status' => '0',
        'year' => $year,
    ]);

    expect($ctx['hasCustomRange'])->toBeTrue();
    expect($ctx['status'])->toBe(Produksi::STATUS_PRODUKSI);
    expect($ctx['periodLabel'])->toContain("{$year}-03-01")
        ->and($ctx['periodLabel'])->toContain('Produksi');

    $summary = $stats->potongWorkerSummary($ctx['startDate'], $ctx['endDate'], $ctx['status']);

    expect($summary)->toHaveCount(1);
    expect($summary->first()->total_qty)->toBe(1234);

    $unfilteredRange = $stats->potongWorkerSummary($ctx['startDate'], $ctx['endDate']);
    expect($unfilteredRange->first()->total_qty)->toBe(1311);
});

it('renders produksi report date and status filters including status zero', function () {
    $potong = Worker::create(['name' => 'Report Cutter', 'type' => Worker::TYPE_POTONG]);
    $year = (int) date('Y');

    Produksi::create([
        'temp_name' => 'Filterable Shirt',
        'quantity' => 3210,
        'potong_id' => $potong->id,
        'potong_date' => "{$year}-03-08",
        'status' => Produksi::STATUS_PRODUKSI,
    ]);
    Produksi::create([
        'temp_name' => 'Later Shirt',
        'quantity' => 9999,
        'potong_id' => $potong->id,
        'potong_date' => "{$year}-08-01",
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $this->actingAs($this->user)
        ->get("/reports/produksi-potong?from={$year}-03-01&to={$year}-03-31&status=0")
        ->assertSuccessful()
        ->assertSee('data-testid="produksi-report-from"', false)
        ->assertSee('data-testid="produksi-report-status"', false)
        ->assertSee('Filterable Shirt')
        ->assertDontSee('Later Shirt')
        ->assertSee('3,210')
        ->assertSee('option value="0"', false);
});

it('denies potong statistics without permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-potong']);

    $this->actingAs($user)->get('/reports/produksi-potong')->assertForbidden();
});

it('allows potong statistics with permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-potong']);
    $user->givePermissionTo(Report::getPermissions()['view-produksi-potong']);

    $this->actingAs($user)
        ->get('/reports/produksi-potong')
        ->assertSuccessful()
        ->assertSee('Statistik Potong');
});

it('allows qc statistics with permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-qc']);
    $user->givePermissionTo(Report::getPermissions()['view-produksi-qc']);

    $this->actingAs($user)
        ->get('/reports/produksi-qc')
        ->assertSuccessful()
        ->assertSee('Statistik QC');
});

it('denies jahit statistics without permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-jahit']);

    $this->actingAs($user)->get('/reports/produksi-jahit')->assertForbidden();
});

it('allows jahit statistics with permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-jahit']);
    $user->givePermissionTo(Report::getPermissions()['view-produksi-jahit']);

    $this->actingAs($user)
        ->get('/reports/produksi-jahit')
        ->assertSuccessful()
        ->assertSee('Statistik Jahit');
});

it('allows pritil statistics with permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate(Report::getPermissions()['view-produksi-pritil']);
    $user->givePermissionTo(Report::getPermissions()['view-produksi-pritil']);

    $this->actingAs($user)
        ->get('/reports/produksi-pritil')
        ->assertSuccessful()
        ->assertSee('Statistik Pritil');
});
