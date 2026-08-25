<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use App\Services\Tax\FakturPajakDirectionResolver;
use App\Services\Tax\FakturPajakPdfParser;
use App\Services\Tax\ParsedFakturPajak;
use App\Services\Tax\TaxFakturImportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo([
        'report-tax-faktur',
        'report-tax-faktur-import',
    ]);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100000_install_tax_faktur_imports_table.php',
        '--force' => true,
    ]);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100100_add_payment_schedule_to_customers_table.php',
        '--force' => true,
    ]);
});

it('calculates expected payment on due day next month after faktur', function () {
    $calc = app(ExpectedPaymentDateCalculator::class);

    $date = $calc->fromFakturDate(Carbon::parse('2026-07-31'), 15);

    expect($date?->toDateString())->toBe('2026-08-15');

    $central = $calc->fromFakturDate(Carbon::parse('2026-07-31'), 6);

    expect($central?->toDateString())->toBe('2026-08-06');
});

it('scopes payment overdue imports using counterparty grace days', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-overdue',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $mds = Addrbook::factory()->customer()->create([
        'payment_due_day' => 15,
        'payment_grace_days' => 7,
    ]);
    $central = Addrbook::factory()->customer()->create([
        'payment_due_day' => 6,
        'payment_grace_days' => 7,
    ]);

    TaxFakturImport::create([
        'faktur_number' => '01000111111111111',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $mds->id,
        'seller_name' => 'Seller',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 1000,
        'ppn' => 110,
        'report_year' => 2026,
        'report_month' => 7,
        'expected_payment_date' => '2026-08-15',
        'user_id' => $this->user->id,
    ]);

    TaxFakturImport::create([
        'faktur_number' => '01000222222222222',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $central->id,
        'seller_name' => 'Seller',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'Central',
        'buyer_npwp' => '0013179569054001',
        'dpp' => 2000,
        'ppn' => 220,
        'report_year' => 2026,
        'report_month' => 7,
        'expected_payment_date' => '2026-08-06',
        'user_id' => $this->user->id,
    ]);

    Carbon::setTestNow('2026-08-23');

    $overdueIds = TaxFakturImport::query()
        ->paymentOverdue()
        ->pluck('faktur_number')
        ->all();

    expect($overdueIds)->toContain('01000111111111111', '01000222222222222');

    Carbon::setTestNow('2026-08-13');

    $stillOk = TaxFakturImport::query()
        ->paymentOverdue()
        ->pluck('faktur_number')
        ->all();

    expect($stillOk)->toBe([]);

    Carbon::setTestNow();
});

it('marks payment overdue after expected date plus grace days', function () {
    $calc = app(ExpectedPaymentDateCalculator::class);

    $expected = Carbon::parse('2026-08-15');
    Carbon::setTestNow('2026-08-23');

    expect($calc->isOverdue($expected, null, 7))->toBeTrue();

    Carbon::setTestNow('2026-08-21');

    expect($calc->isOverdue($expected, null, 7))->toBeFalse();

    Carbon::setTestNow();
});

it('imports keluaran faktur and includes it in ppn ringkasan', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-import',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $mds = Addrbook::factory()->customer()->create([
        'name' => 'MDS RETAILING TBK',
        'npwp' => '0013179569054000',
        'payment_due_day' => 15,
        'payment_grace_days' => 7,
    ]);

    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600298450234',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta Utara',
        sellerName: 'INDOSPORT ADIGUNA PERKASA',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS RETAILING TBK',
        buyerNpwp: '0013179569054000',
        grossTotal: 21_221_157.0,
        discountTotal: 0,
        dpp: 19_452_728.0,
        ppn: 2_334_327.0,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
    );

    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($parsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $mds->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'variance_expense_addrbook_id' => null,
    ]);

    expect($import->expected_payment_date?->toDateString())->toBe('2026-08-15')
        ->and((float) $import->payment_variance)->toBe(-1_787_055.0);

    $ringkasan = app(TaxReportService::class)->ringkasan(2026, 7, $entity->id);

    expect($ringkasan['keluaran_dpp'])->toBe(19_452_728.0)
        ->and($ringkasan['keluaran_tax'])->toBe(2_334_327.0);
});

it('suggests keluaran when entity npwp matches seller', function () {
    ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-dir',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);

    $parsed = app(FakturPajakPdfParser::class)->parseFile(
        base_path('tests/Fixtures/faktur-pajak/mds-output-tax-invoice-sample.pdf'),
    );

    $suggestion = app(FakturPajakDirectionResolver::class)->suggest($parsed);

    expect($suggestion['direction'])->toBe(FakturPajakDirectionResolver::DIRECTION_KELUARAN)
        ->and($suggestion['matched_side'])->toBe('seller');
});

it('renders faktur import pages', function () {
    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.index'))
        ->assertOk()
        ->assertSee('Faktur Pajak', false);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.create'))
        ->assertOk()
        ->assertSee('Upload Faktur Pajak PDF', false);
});

it('shows historical import detail page', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-show',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $mds = Addrbook::factory()->customer()->create(['name' => 'MDS RETAILING TBK']);

    $import = TaxFakturImport::create([
        'faktur_number' => '01000999999999999',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $mds->id,
        'seller_name' => 'INDOSPORT ADIGUNA PERKASA',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS RETAILING TBK',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 1_000_000,
        'ppn' => 110_000,
        'report_year' => 2026,
        'report_month' => 7,
        'line_items' => [
            ['line_no' => 1, 'name' => 'Test Item', 'quantity' => 2, 'unit_price' => 500_000, 'total' => 1_000_000],
        ],
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.show', $import))
        ->assertOk()
        ->assertSee('01000999999999999', false)
        ->assertSee('Test Item', false);
});

it('forbids faktur list without report-tax-faktur permission', function () {
    $restricted = User::factory()->create();

    $this->actingAs($restricted)
        ->get(route('reports.tax.faktur.index'))
        ->assertForbidden();
});

it('allows view-only user to list and show but not upload', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('report-tax-faktur');

    $entity = ReportingEntity::create([
        'name' => 'PT Viewer',
        'slug' => 'pt-viewer-faktur',
        'is_pkp' => true,
    ]);
    $customer = Addrbook::factory()->customer()->create();
    $import = TaxFakturImport::create([
        'faktur_number' => '01000888888888888',
        'faktur_date' => '2026-07-01',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'Seller',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'Buyer',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 100,
        'ppn' => 11,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('reports.tax.faktur.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('reports.tax.faktur.show', $import))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('reports.tax.faktur.create'))
        ->assertForbidden();
});
