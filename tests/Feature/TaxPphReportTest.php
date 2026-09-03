<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\PphFinalReportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-tax-pph');
});

function seedPphFinalScenario(): array
{
    $nonPkp = ReportingEntity::create(['name' => 'Pribadi PPh', 'slug' => 'pribadi-pph', 'is_pkp' => false]);
    $pkp = ReportingEntity::create(['name' => 'CV PKP PPh', 'slug' => 'cv-pkp-pph', 'is_pkp' => true]);
    $nonPkpBank = Addrbook::create(['name' => 'BCA Pribadi PPh', 'type' => Addrbook::TYPE_BANK]);
    $pkpBank = Addrbook::create(['name' => 'BCA PKP PPh', 'type' => Addrbook::TYPE_BANK]);
    $nonPkp->banks()->attach($nonPkpBank->id, ['is_active' => true]);
    $pkp->banks()->attach($pkpBank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko PPh']);

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-10',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $nonPkpBank->id,
        'invoice' => 'CIN-PPH-1',
        'total' => 200_000,
        'real_total' => 200_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-11',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $pkpBank->id,
        'invoice' => 'CIN-PKP-1',
        'total' => 111_000,
        'real_total' => 111_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    ReportingMonthlyTaxSummary::create([
        'year' => 2025,
        'month' => 6,
        'reporting_entity_id' => $nonPkp->id,
        'pph_final' => 1_000,
        'tax_paid' => -500,
    ]);

    ReportingMonthlyTaxSummary::create([
        'year' => 2024,
        'month' => 12,
        'reporting_entity_id' => $nonPkp->id,
        'pph_final' => 99_999,
    ]);

    return compact('nonPkp', 'pkp', 'customer');
}

it('renders pph final from non-pkp cash in and hides pkp entities', function () {
    $data = seedPphFinalScenario();
    $report = app(PphFinalReportService::class)->build(2025, 6, $data['nonPkp']->id);

    expect($report['gross_cash_in'])->toBe(200_000.0)
        ->and($report['net_omzet'])->toBe(200_000.0)
        ->and($report['pph_final'])->toBe(1_000.0)
        ->and($report['tax_paid'])->toBe(-500.0)
        ->and($report['rows']->pluck('party')->all())->toBe(['Toko PPh'])
        ->and($report['rows']->first()['net_omzet'])->toBe(200_000.0);

    $this->actingAs($this->user)
        ->get(route('reports.tax.pph', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['nonPkp']->id,
        ]))
        ->assertOk()
        ->assertSee('Laporan PPh Final', false)
        ->assertSee('Toko PPh', false)
        ->assertSee('200,000', false)
        ->assertSee('1,000', false)
        ->assertSee('Net omzet', false)
        ->assertDontSee('CIN-PKP-1', false)
        ->assertDontSee('CV PKP PPh', false);
});

it('omits 2024 from pph final summaries and drill-down', function () {
    $data = seedPphFinalScenario();
    $service = app(PphFinalReportService::class);

    expect($service->build(2024, 12, $data['nonPkp']->id)['pph_final'])->toBe(0.0)
        ->and($service->build(2024, 12, $data['nonPkp']->id)['rows'])->toBeEmpty();
});

it('excludes pkp entities from the pph final entity list', function () {
    seedPphFinalScenario();

    $names = app(PphFinalReportService::class)->nonPkpEntities()->pluck('name');

    expect($names)->toContain('Pribadi PPh')
        ->and($names)->not->toContain('CV PKP PPh');
});

it('exports pph final as csv and xlsx', function () {
    $data = seedPphFinalScenario();

    $csv = $this->actingAs($this->user)
        ->get(route('reports.tax.pph', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['nonPkp']->id,
            'export' => 'csv',
        ]));

    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv');
    expect($csv->streamedContent())
        ->toContain('Laporan PPh Final')
        ->toContain('Toko PPh')
        ->toContain('Net Omzet');

    $xlsx = $this->actingAs($this->user)
        ->get(route('reports.tax.pph', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['nonPkp']->id,
            'export' => 'xlsx',
        ]));

    $xlsx->assertOk();
    expect($xlsx->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmp = tempnam(sys_get_temp_dir(), 'pph-xlsx-');
    file_put_contents($tmp, $xlsx->streamedContent());
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    $values = collect($sheet->toArray())->flatten()->filter();
    expect($values->all())->toContain('Laporan PPh Final')
        ->and($values->all())->toContain('Toko PPh');
});

it('forbids users without report-tax-pph permission', function () {
    $restricted = User::factory()->create();
    expect($restricted->is_superadmin)->toBeFalse();

    $this->actingAs($restricted)
        ->get(route('reports.tax.pph'))
        ->assertForbidden();
});
