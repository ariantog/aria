<?php

use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\User;
use App\Services\PermissionGenerator;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo([
        'report-tax-ppn',
        'report-neraca',
        'report-laba-rugi',
        'report-receivables',
        'report-payables',
    ]);
});

function loadReportSpreadsheet($response)
{
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmp = tempnam(sys_get_temp_dir(), 'report-xlsx-');
    file_put_contents($tmp, $response->streamedContent());
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    return $sheet;
}

it('exports ppn neraca laba rugi and aging reports as xlsx', function (string $route, array $query, string $expectedTitle) {
    $entity = ReportingEntity::create(['name' => 'CV Excel', 'slug' => 'cv-excel', 'is_pkp' => true]);
    ReportingMonthlyTaxSummary::create([
        'year' => 2025,
        'month' => 6,
        'reporting_entity_id' => $entity->id,
        'ppn_keluaran_dpp' => 100_000,
        'ppn_keluaran_tax' => 11_000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route($route, array_merge($query, ['export' => 'xlsx'])));

    $response->assertOk();
    $sheet = loadReportSpreadsheet($response);
    $values = collect($sheet->toArray())->flatten()->filter(fn ($value) => $value !== null && $value !== '');

    expect($values->all())->toContain($expectedTitle);
})->with([
    'ppn' => ['reports.tax.ppn', ['year' => 2025, 'month' => 6], 'Laporan PPN'],
    'neraca' => ['reports.neraca', ['year' => 2026, 'month' => 1], 'Neraca'],
    'laba rugi' => ['reports.laba-rugi', ['year' => 2026, 'month' => 1, 'months' => 1], 'Laporan Laba Rugi'],
    'piutang' => ['reports.receivables', ['year' => 2026, 'month' => 1], 'Piutang Usaha'],
    'hutang' => ['reports.payables', ['year' => 2026, 'month' => 1], 'Hutang Usaha'],
]);

it('shows export excel links on financial report pages', function (string $route, string $testId) {
    $this->actingAs($this->user)
        ->get(route($route, ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('data-testid="'.$testId.'"', false)
        ->assertSee('Export Excel', false);
})->with([
    'ppn' => ['reports.tax.ppn', 'ppn-export-xlsx'],
    'neraca' => ['reports.neraca', 'neraca-export-xlsx'],
    'laba rugi' => ['reports.laba-rugi', 'laba-rugi-export-xlsx'],
    'piutang' => ['reports.receivables', 'aging-export-xlsx'],
    'hutang' => ['reports.payables', 'aging-export-xlsx'],
]);

it('forbids xlsx export without the matching report permission', function (string $route, string $permission) {
    $restricted = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    expect($restricted->is_superadmin)->toBeFalse();

    $this->actingAs($restricted)
        ->get(route($route, ['year' => 2026, 'month' => 1, 'export' => 'xlsx']))
        ->assertForbidden();

    $restricted->givePermissionTo($permission);

    $this->actingAs($restricted)
        ->get(route($route, ['year' => 2026, 'month' => 1, 'export' => 'xlsx']))
        ->assertOk();
})->with([
    'ppn' => ['reports.tax.ppn', 'report-tax-ppn'],
    'neraca' => ['reports.neraca', 'report-neraca'],
    'laba rugi' => ['reports.laba-rugi', 'report-laba-rugi'],
    'piutang' => ['reports.receivables', 'report-receivables'],
    'hutang' => ['reports.payables', 'report-payables'],
]);
