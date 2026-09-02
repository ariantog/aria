<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use App\Services\Tax\FakturPajakDirectionResolver;
use App\Services\Tax\FakturPajakPdfParser;
use App\Services\Tax\LinkFakturSells;
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
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100200_add_variance_transaction_id_to_tax_faktur_imports_table.php',
        '--force' => true,
    ]);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_27_100000_add_sell_transaction_id_to_tax_faktur_imports_table.php',
        '--force' => true,
    ]);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_31_120000_install_tax_faktur_import_sells_table.php',
        '--force' => true,
    ]);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_09_02_180000_add_down_payment_total_to_tax_faktur_imports_table.php',
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
        ->and((float) $import->down_payment_total)->toBe(0.0)
        ->and((float) $import->fakturGross())->toBe(23_555_484.0)
        ->and((float) $import->payment_variance)->toBe(-3_555_484.0);

    $ringkasan = app(TaxReportService::class)->ringkasan(2026, 7, $entity->id);

    expect($ringkasan['keluaran_dpp'])->toBe(19_452_728.0)
        ->and($ringkasan['keluaran_tax'])->toBe(2_334_327.0);
});

it('subtracts potongan and uang muka from payable total and ignores ppnbm', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-uang-muka',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $customer = Addrbook::factory()->customer()->create();

    $parsed = new ParsedFakturPajak(
        fakturNumber: '01000UANGMUKA00001',
        fakturDate: Carbon::parse('2026-07-15'),
        fakturDatePlace: 'Jakarta',
        sellerName: 'INDOSPORT',
        sellerNpwp: '0504330085044000',
        buyerName: 'Buyer',
        buyerNpwp: '0013179569054000',
        grossTotal: 1_200_000.0,
        discountTotal: 50_000.0,
        dpp: 916_667.0,
        ppn: 110_000.0,
        ppnbm: 99_000.0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
        downPaymentTotal: 150_000.0,
    );

    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($parsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
    ]);

    expect((float) $import->down_payment_total)->toBe(150_000.0)
        ->and((float) $import->fakturGross())->toBe(1_110_000.0)
        ->and($import->fakturGross())->not->toBe((float) $import->dpp + (float) $import->ppn)
        ->and($import->fakturGross())->not->toBe((float) $import->gross_total + (float) $import->ppn + (float) $import->ppnbm);
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

    $this->actingAs($viewer)
        ->get(route('reports.tax.faktur.show', $import))
        ->assertOk()
        ->assertDontSee('data-testid="faktur-delete-form"', false);

    $this->actingAs($viewer)
        ->delete(route('reports.tax.faktur.destroy', $import))
        ->assertForbidden();
});

it('forbids faktur import without report-tax-faktur-import permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('report-tax-faktur');

    $this->actingAs($viewer)
        ->post(route('reports.tax.faktur.parse'), [])
        ->assertForbidden();
});

it('suggests cash in by customer and entity bank', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-cashin',
        'is_pkp' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA Entity', 'type' => Addrbook::TYPE_BANK]);
    $otherBank = Addrbook::create(['name' => 'Other Bank', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $customer = Addrbook::factory()->customer()->create(['name' => 'MDS RETAILING TBK']);
    $otherCustomer = Addrbook::factory()->customer()->create();

    $preferred = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'invoice' => '04002600298450234',
        'total' => 20_000_000,
        'real_total' => 20_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $otherBank->id,
        'total' => 20_000_000,
        'real_total' => 20_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $otherCustomer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'total' => 20_000_000,
        'real_total' => 20_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $wrongCustomerCashIn = Transaction::withoutEvents(fn () => Transaction::query()->latest('id')->first());

    $response = $this->actingAs($this->user)
        ->getJson(route('reports.tax.faktur.cash-in-suggestions', [
            'counterparty_id' => $customer->id,
            'reporting_entity_id' => $entity->id,
            'payment_received_amount' => 20_000_000,
            'payment_received_date' => '2026-08-15',
            'faktur_number' => '04002600298450234',
        ]));

    $response->assertOk();
    $ids = collect($response->json('suggestions'))->pluck('id')->all();

    expect($ids[0] ?? null)->toBe($preferred->id)
        ->and($ids)->not->toContain($wrongCustomerCashIn->id);
});

it('posts payment variance as cash out to expense ledger', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-variance',
        'is_pkp' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA Variance', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create();
    $expense = Addrbook::create(['name' => 'Biaya MDS', 'type' => Addrbook::TYPE_ACCOUNT]);

    $cashIn = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'total' => 20_000_000,
        'real_total' => 20_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600999999999',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta',
        sellerName: 'INDOSPORT',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS',
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
        'counterparty_id' => $customer->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $cashIn->id,
        'variance_expense_addrbook_id' => $expense->id,
    ]);

    expect($import->cash_in_transaction_id)->toBe($cashIn->id)
        ->and($import->variance_transaction_id)->not->toBeNull();

    $varianceTx = Transaction::query()->find($import->variance_transaction_id);

    expect($varianceTx)->not->toBeNull()
        ->and((int) $varianceTx->type)->toBe(Transaction::TYPE_CASH_OUT)
        ->and((int) $varianceTx->sender_id)->toBe($bank->id)
        ->and((int) $varianceTx->receiver_id)->toBe($expense->id)
        ->and(abs((float) $varianceTx->total))->toBe(3_555_484.0);
});

function seedKeluaranLinkFilterScenario(User $user): array
{
    $entity = ReportingEntity::create([
        'name' => 'PT Link Filter',
        'slug' => 'pt-link-filter-'.uniqid(),
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['name' => 'MDS Filter']);
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Supplier Filter']);

    $unlinked = TaxFakturImport::create([
        'faktur_number' => '01000UNLINKED00001',
        'faktur_date' => '2026-07-10',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS Filter',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 5_000_000,
        'ppn' => 550_000,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $user->id,
    ]);

    $complete = TaxFakturImport::create([
        'faktur_number' => '01000COMPLETE00001',
        'faktur_date' => '2026-07-11',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS Filter',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 2_000_000,
        'ppn' => 220_000,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $user->id,
    ]);
    $completeSell = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-07-11',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'INV-COMPLETE',
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 2_000_000),
        'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, 2_220_000),
        'ppn' => 220_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));
    app(LinkFakturSells::class)->attach($complete, [$completeSell->id]);

    $short = TaxFakturImport::create([
        'faktur_number' => '01000SHORTDPP00001',
        'faktur_date' => '2026-07-12',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS Filter',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 10_000_000,
        'ppn' => 1_100_000,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $user->id,
    ]);
    $shortSell = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-07-12',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'INV-SHORT',
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 4_000_000),
        'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, 4_440_000),
        'ppn' => 440_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));
    app(LinkFakturSells::class)->attach($short, [$shortSell->id]);

    $masukan = TaxFakturImport::create([
        'faktur_number' => '01000MASUKAN000001',
        'faktur_date' => '2026-07-13',
        'direction' => TaxFakturImport::DIRECTION_MASUKAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $supplier->id,
        'seller_name' => 'Supplier Filter',
        'seller_npwp' => '0013179569054999',
        'buyer_name' => 'INDOSPORT',
        'buyer_npwp' => '0504330085044000',
        'dpp' => 3_000_000,
        'ppn' => 330_000,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $user->id,
    ]);

    return compact('entity', 'warehouse', 'customer', 'unlinked', 'complete', 'short', 'masukan', 'completeSell', 'shortSell');
}

it('scopes keluaran without linked sells and with short linked dpp', function () {
    $data = seedKeluaranLinkFilterScenario($this->user);

    $unlinked = TaxFakturImport::query()->keluaranWithoutLinkedSells()->pluck('faktur_number')->all();
    expect($unlinked)->toContain('01000UNLINKED00001')
        ->and($unlinked)->not->toContain('01000COMPLETE00001', '01000SHORTDPP00001', '01000MASUKAN000001');

    $remaining = TaxFakturImport::query()->keluaranWithShortLinkedDpp()->pluck('faktur_number')->all();
    expect($remaining)->toContain('01000SHORTDPP00001')
        ->and($remaining)->not->toContain('01000UNLINKED00001', '01000COMPLETE00001', '01000MASUKAN000001');

    $incomplete = TaxFakturImport::query()->keluaranNeedingSellCoverage()->pluck('faktur_number')->all();
    expect($incomplete)->toContain('01000UNLINKED00001', '01000SHORTDPP00001')
        ->and($incomplete)->not->toContain('01000COMPLETE00001', '01000MASUKAN000001');

    $short = $data['short']->fresh(['sellTransactions']);
    expect($short->linkedSellDpp())->toBe(4_000_000.0)
        ->and($short->remainingSellDpp())->toBe(6_000_000.0)
        ->and($short->hasShortLinkedDpp())->toBeTrue();

    $complete = $data['complete']->fresh(['sellTransactions']);
    expect($complete->remainingSellDpp())->toBe(0.0)
        ->and($complete->hasShortLinkedDpp())->toBeFalse();
});

it('lists unlinked keluaran on the faktur index', function () {
    seedKeluaranLinkFilterScenario($this->user);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.index', ['link' => TaxFakturImport::LINK_FILTER_UNLINKED]))
        ->assertOk()
        ->assertSee('data-testid="faktur-link-filter"', false)
        ->assertSee('data-testid="faktur-filter-link-unlinked"', false)
        ->assertSee('01000UNLINKED00001', false)
        ->assertSee('Belum di-link', false)
        ->assertDontSee('01000COMPLETE00001', false)
        ->assertDontSee('01000SHORTDPP00001', false)
        ->assertDontSee('01000MASUKAN000001', false);
});

it('lists keluaran whose linked dpp is still short of faktur dpp', function () {
    seedKeluaranLinkFilterScenario($this->user);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.index', ['link' => TaxFakturImport::LINK_FILTER_REMAINING]))
        ->assertOk()
        ->assertSee('01000SHORTDPP00001', false)
        ->assertSee('DPP kurang', false)
        ->assertDontSee('01000UNLINKED00001', false)
        ->assertDontSee('01000COMPLETE00001', false)
        ->assertDontSee('01000MASUKAN000001', false);
});

it('lists keluaran that still need sell coverage', function () {
    seedKeluaranLinkFilterScenario($this->user);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.index', ['link' => TaxFakturImport::LINK_FILTER_INCOMPLETE]))
        ->assertOk()
        ->assertSee('01000UNLINKED00001', false)
        ->assertSee('01000SHORTDPP00001', false)
        ->assertDontSee('01000COMPLETE00001', false)
        ->assertDontSee('01000MASUKAN000001', false);
});

it('returns customer reseller and ledger matches for faktur counterparty lookup', function () {
    $customer = Addrbook::factory()->customer()->create(['name' => 'Zeta Faktur Customer']);
    $reseller = Addrbook::factory()->create(['name' => 'Zeta Faktur Reseller', 'type' => Addrbook::TYPE_RESELLER]);
    $ledger = Addrbook::factory()->create(['name' => 'Zeta Faktur Ledger', 'type' => Addrbook::TYPE_ACCOUNT]);
    Addrbook::factory()->supplier()->create(['name' => 'Zeta Faktur Supplier']);

    $names = collect($this->actingAs($this->user)
        ->getJson(route('reports.tax.faktur.counterparty-lookup', ['search' => 'Zeta Faktur']))
        ->assertOk()
        ->json())
        ->pluck('name');

    expect($names)->toContain('Zeta Faktur Customer')
        ->toContain('Zeta Faktur Reseller')
        ->toContain('Zeta Faktur Ledger')
        ->not->toContain('Zeta Faktur Supplier');
});

it('rejects faktur counterparty lookup without import permission', function () {
    $user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $user->givePermissionTo('report-tax-faktur');

    $this->actingAs($user)
        ->getJson(route('reports.tax.faktur.counterparty-lookup', ['search' => 'Test']))
        ->assertForbidden();
});

it('deletes an imported faktur so the number can be uploaded again', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT Delete Faktur',
        'slug' => 'pt-delete-faktur',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $customer = Addrbook::factory()->customer()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();

    $import = TaxFakturImport::create([
        'faktur_number' => '01000DELETE0000001',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS',
        'buyer_npwp' => '0013179569054000',
        'gross_total' => 21_221_157,
        'dpp' => 19_452_728,
        'ppn' => 2_334_327,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $this->user->id,
    ]);

    $sell = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-07-31',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'INV-KEEP',
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 19_452_728),
        'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, 23_555_484),
        'ppn' => 2_334_327,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    app(LinkFakturSells::class)->attach($import, [$sell->id]);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.show', $import))
        ->assertOk()
        ->assertSee('data-testid="faktur-delete-form"', false)
        ->assertSee('data-testid="faktur-amount-rows"', false)
        ->assertSee('Harga jual / penggantian / uang muka / termin', false)
        ->assertSee('Dikurangi uang muka yang telah diterima', false);

    $this->actingAs($this->user)
        ->delete(route('reports.tax.faktur.destroy', $import))
        ->assertRedirect(route('reports.tax.faktur.index'));

    expect(TaxFakturImport::query()->where('faktur_number', '01000DELETE0000001')->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($sell->id)->exists())->toBeTrue();

    $parsed = new ParsedFakturPajak(
        fakturNumber: '01000DELETE0000001',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta',
        sellerName: 'INDOSPORT',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS',
        buyerNpwp: '0013179569054000',
        grossTotal: 21_221_157.0,
        discountTotal: 0,
        dpp: 19_452_728.0,
        ppn: 2_334_327.0,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
    );

    $reimported = app(TaxFakturImportService::class)->storeFromParsed($parsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
    ]);

    expect($reimported->faktur_number)->toBe('01000DELETE0000001')
        ->and((float) $reimported->fakturGross())->toBe(23_555_484.0);
});

it('forbids deleting a faktur without import permission', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT No Delete',
        'slug' => 'pt-no-delete-faktur',
        'is_pkp' => true,
    ]);
    $customer = Addrbook::factory()->customer()->create();
    $import = TaxFakturImport::create([
        'faktur_number' => '01000NODELETE00001',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 1_000_000,
        'ppn' => 110_000,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => $this->user->id,
    ]);

    $viewer = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $viewer->givePermissionTo('report-tax-faktur');

    $this->actingAs($viewer)
        ->delete(route('reports.tax.faktur.destroy', $import))
        ->assertForbidden();

    expect(TaxFakturImport::query()->whereKey($import->id)->exists())->toBeTrue();
});
