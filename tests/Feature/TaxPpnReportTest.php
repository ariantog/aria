<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\TaxReportService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-tax-ppn');
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100000_install_tax_faktur_imports_table.php',
        '--force' => true,
    ]);
});

function seedPpnReportScenario(): array
{
    $entityA = ReportingEntity::create(['name' => 'CV Test A', 'slug' => 'cv-test-a', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'CV Test B', 'slug' => 'cv-test-b', 'is_pkp' => true]);
    $bankA = Addrbook::create(['name' => 'BCA A', 'type' => Addrbook::TYPE_BANK]);
    $bankB = Addrbook::create(['name' => 'BCA B', 'type' => Addrbook::TYPE_BANK]);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);

    ReportingMonthlyTaxSummary::create([
        'year' => 2025,
        'month' => 6,
        'reporting_entity_id' => $entityA->id,
        'ppn_keluaran_dpp' => 100_000,
        'ppn_keluaran_tax' => 11_000,
        'ppn_masukan_dpp' => 50_000,
        'ppn_masukan_tax' => 5_500,
        'retur_keluaran_dpp' => 10_000,
        'retur_keluaran_tax' => 1_100,
    ]);

    ReportingMonthlyTaxSummary::create([
        'year' => 2025,
        'month' => 6,
        'reporting_entity_id' => $entityB->id,
        'ppn_keluaran_dpp' => 20_000,
        'ppn_keluaran_tax' => 2_200,
        'ppn_masukan_dpp' => 0,
        'ppn_masukan_tax' => 0,
    ]);

    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true, 'name' => 'Supplier PPN']);
    $customer = Addrbook::factory()->customer()->create(['ppn' => true, 'name' => 'Customer PPN']);
    $warehouse = Addrbook::factory()->warehouse()->create();

    $buy = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-10',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplier->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'invoice' => 'BUY-PPN-1',
        'total' => 50_000,
        'real_total' => 55_500,
        'ppn' => 5_500,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-11',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bankA->id,
        'receiver_type' => Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $supplier->id,
        'invoice' => 'BUY-PPN-1',
        'total' => -55_500,
        'real_total' => -55_500,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $sell = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-12',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'SELL-PPN-1',
        'total' => -20_000,
        'real_total' => -22_200,
        'ppn' => 2_200,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-13',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bankA->id,
        'invoice' => 'SELL-PPN-1',
        'total' => 22_200,
        'real_total' => 22_200,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-06-14',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bankA->id,
        'invoice' => 'MP-ONLY',
        'total' => 111_000,
        'real_total' => 111_000,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    return compact('entityA', 'entityB', 'buy', 'sell');
}

it('renders the ppn tax report with ringkasan from monthly summaries', function () {
    $data = seedPpnReportScenario();

    $ringkasan = app(TaxReportService::class)->ringkasan(2025, 6, $data['entityA']->id);
    expect($ringkasan['keluaran_dpp'])->toBe(90_000.0)
        ->and($ringkasan['keluaran_tax'])->toBe(9_900.0)
        ->and($ringkasan['masukan_dpp'])->toBe(50_000.0)
        ->and($ringkasan['masukan_tax'])->toBe(5_500.0)
        ->and($ringkasan['net_ppn'])->toBe(4_400.0)
        ->and($ringkasan['retur_keluaran_tax'])->toBe(1_100.0);

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['entityA']->id,
        ]))
        ->assertOk()
        ->assertSee('Laporan PPN', false)
        ->assertSee('Ringkasan', false)
        ->assertSee('Keluaran', false)
        ->assertSee('Masukan', false)
        ->assertSee('90,000', false)
        ->assertSee('9,900', false)
        ->assertSee('50,000', false)
        ->assertSee('5,500', false)
        ->assertSee('4,400', false);
});

it('aggregates consolidated entity summaries', function () {
    $data = seedPpnReportScenario();

    $service = app(TaxReportService::class);
    $ringkasan = $service->ringkasan(2025, 6, TaxReportService::CONSOLIDATED_ENTITY);

    expect($ringkasan['keluaran_dpp'])->toBe(110_000.0)
        ->and($ringkasan['keluaran_tax'])->toBe(12_100.0)
        ->and($ringkasan['masukan_dpp'])->toBe(50_000.0)
        ->and($ringkasan['masukan_tax'])->toBe(5_500.0)
        ->and($ringkasan['net_ppn'])->toBe(6_600.0);

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => TaxReportService::CONSOLIDATED_ENTITY,
        ]))
        ->assertOk()
        ->assertSee('Konsolidasi', false)
        ->assertSee('CV Test A', false)
        ->assertSee('CV Test B', false);
});

it('lists keluaran drill-down from sell not customer cash in', function () {
    $data = seedPpnReportScenario();

    $service = app(TaxReportService::class);
    $rows = $service->keluaranRows(2025, 6, $data['entityA']->id);

    expect($rows->pluck('type')->all())->toContain('sell')
        ->and($rows->pluck('type')->all())->not->toContain('cash_in')
        ->and($rows->where('type', 'sell')->first()['invoice'])->toBe('SELL-PPN-1');

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['entityA']->id,
        ]))
        ->assertOk()
        ->assertSee('SELL-PPN-1', false)
        ->assertSee('Customer PPN', false);
});

it('lists masukan drill-down from buy payments', function () {
    $data = seedPpnReportScenario();

    $service = app(TaxReportService::class);
    $rows = $service->masukanRows(2025, 6, $data['entityA']->id);

    expect($rows->count())->toBe(1)
        ->and($rows->first()['type'])->toBe('buy')
        ->and($rows->first()['invoice'])->toBe('BUY-PPN-1');

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['entityA']->id,
        ]))
        ->assertOk()
        ->assertSee('BUY-PPN-1', false)
        ->assertSee('Supplier PPN', false);
});

it('omits pre-2025 data from summaries and drill-down', function () {
    $entity = ReportingEntity::create(['name' => 'Legacy Entity', 'slug' => 'legacy-entity', 'is_pkp' => true]);

    ReportingMonthlyTaxSummary::create([
        'year' => 2024,
        'month' => 12,
        'reporting_entity_id' => $entity->id,
        'ppn_keluaran_tax' => 99_999,
    ]);

    $service = app(TaxReportService::class);

    expect($service->ringkasan(2024, 12, $entity->id)['keluaran_tax'])->toBe(0.0)
        ->and($service->keluaranRows(2024, 12, $entity->id))->toBeEmpty()
        ->and($service->masukanRows(2024, 12, $entity->id))->toBeEmpty();

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', ['year' => 2024, 'month' => 12, 'entity' => $entity->id]))
        ->assertOk()
        ->assertDontSee('99,999', false);
});

it('exports ppn report as csv', function () {
    $data = seedPpnReportScenario();

    $response = $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => $data['entityA']->id,
            'export' => 'csv',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Ringkasan')
        ->toContain('Keluaran')
        ->toContain('Masukan')
        ->toContain('BUY-PPN-1')
        ->toContain('SELL-PPN-1');
});

it('forbids users without report-tax-ppn permission', function () {
    $restricted = User::factory()->create();
    $this->actingAs($restricted)
        ->get(route('reports.tax.ppn'))
        ->assertForbidden();
});

it('includes imported faktur rows in keluaran drill-down', function () {
    $entity = ReportingEntity::create(['name' => 'PT Faktur', 'slug' => 'pt-faktur', 'is_pkp' => true]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'MDS RETAILING TBK']);

    TaxFakturImport::create([
        'faktur_number' => '01000123456789012',
        'faktur_date' => '2025-06-20',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'Seller',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 1_000_000,
        'ppn' => 110_000,
        'report_year' => 2025,
        'report_month' => 6,
        'source_format' => 'mds_output_tax_invoice',
        'user_id' => $this->user->id,
    ]);

    $service = app(TaxReportService::class);
    $rows = $service->keluaranRows(2025, 6, $entity->id);

    expect($rows->pluck('type')->all())->toContain('faktur_import')
        ->and($rows->firstWhere('type', 'faktur_import')['invoice'])->toBe('01000123456789012')
        ->and($rows->firstWhere('type', 'faktur_import')['source_label'])->toBe('MDS faktur')
        ->and($rows->firstWhere('type', 'faktur_import')['link_type'])->toBe('faktur');

    $this->actingAs($this->user)
        ->get(route('reports.tax.ppn', [
            'year' => 2025,
            'month' => 6,
            'entity' => $entity->id,
        ]))
        ->assertOk()
        ->assertSee('01000123456789012', false)
        ->assertSee('MDS faktur', false)
        ->assertSee('MDS RETAILING TBK', false);
});
