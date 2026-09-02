<?php

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingWarehouseFulfillment;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\Reporting\ReportingSummaryRecorder;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\FakturLineItemMatcher;
use App\Services\Tax\ParsedFakturPajak;
use App\Services\Tax\PostFakturSell;
use App\Services\Tax\TaxFakturImportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Faktur fixtures use 2026-07-31. Freeze "now" in August so book-closing
    // still treats that date as open (current + previous month only).
    Carbon::setTestNow(Carbon::parse('2026-08-31 15:00:00'));
    Bus::fake([UpdateTransactionSummaries::class]);
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo([
        'report-tax-faktur',
        'report-tax-faktur-import',
    ]);
    foreach ([
        'database/migrations/2026_08_25_100000_install_tax_faktur_imports_table.php',
        'database/migrations/2026_08_25_100100_add_payment_schedule_to_customers_table.php',
        'database/migrations/2026_08_25_100200_add_variance_transaction_id_to_tax_faktur_imports_table.php',
        'database/migrations/2026_08_27_100000_add_sell_transaction_id_to_tax_faktur_imports_table.php',
        'database/migrations/2026_08_31_120000_install_tax_faktur_import_sells_table.php',
        'database/migrations/2026_09_02_180000_add_down_payment_total_to_tax_faktur_imports_table.php',
    ] as $path) {
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedConsignmentFakturSellScenario(): array
{
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-sell-post',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $bank = Addrbook::create(['name' => 'BCA Consignment', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Consignment WH']);
    $customer = Addrbook::factory()->customer()->create([
        'name' => 'MDS RETAILING TBK',
        'npwp' => '0013179569054000',
        'payment_due_day' => 15,
        'payment_grace_days' => 7,
        'ppn' => true,
    ]);
    $item = Item::factory()->create([
        'name' => 'Celana Panjang',
        'code' => 'CLN001',
        'price' => 1_000_000,
        'cost' => 500_000,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 100,
    ]);

    $cashIn = Transaction::withoutEvents(fn () => Transaction::create([
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
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

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
        lineItems: [
            ['line_no' => 1, 'name' => 'Celana Panjang', 'quantity' => 18, 'unit_price' => 140_244.95, 'total' => 2_524_409.1],
        ],
    );

    return compact('entity', 'bank', 'warehouse', 'customer', 'item', 'cashIn', 'parsed');
}

it('posts sell from keluaran faktur with faktur dpp and ppn totals', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $sell = app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => PostFakturSell::DATE_SOURCE_FAKTUR,
        'invoice_source' => PostFakturSell::INVOICE_SOURCE_FAKTUR,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    expect($import->fresh()->sell_transaction_id)->toBe($sell->id)
        ->and((int) $sell->type)->toBe(Transaction::TYPE_SELL)
        ->and((float) $sell->total)->toBe(-19_452_728.0)
        ->and((float) $sell->ppn)->toBe(2_334_327.0)
        ->and($sell->invoice)->toBe('04002600298450234')
        ->and($sell->date->toDateString())->toBe('2026-07-31');

    $stock = WarehouseItem::query()
        ->where('warehouse_id', $data['warehouse']->id)
        ->where('item_id', $data['item']->id)
        ->value('quantity');

    expect((float) $stock)->toBe(99.0);

    Bus::assertDispatched(UpdateTransactionSummaries::class, fn ($job) => $job->transactionId === $sell->id);
});

it('matches gross sell to faktur and treats payment gap as consignment margin', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $fakturGross = $data['parsed']->grossIncludingTax();
    $paymentReceived = 20_000_000.0;
    $expectedMargin = round($paymentReceived - $fakturGross, 2);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => $paymentReceived,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    expect((float) $import->fakturGross())->toBe($fakturGross)
        ->and((float) $import->payment_variance)->toBe($expectedMargin)
        ->and(abs((float) $data['cashIn']->total))->toBe($paymentReceived);

    $sell = app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    expect((float) $sell->total)->toBe(-19_452_728.0)
        ->and((float) $sell->ppn)->toBe(2_334_327.0)
        ->and($fakturGross)->toBe(23_555_484.0)
        ->and($fakturGross)->toBeGreaterThan($paymentReceived);
});

it('resolves sell tax entity from linked cash in bank', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $sell = app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    $entity = app(ReportingSummaryRecorder::class)->resolveEntityForSellTax($sell->fresh());

    expect($entity)->not->toBeNull()
        ->and($entity->id)->toBe($data['entity']->id);
});

it('excludes posted faktur from keluaran drill-down to avoid double counting sell ppn', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $beforeRows = app(TaxReportService::class)->keluaranRows(2026, 7, $data['entity']->id);
    expect($beforeRows->pluck('type')->all())->toContain('faktur_import');

    Bus::fake([UpdateTransactionSummaries::class]);

    $sell = app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    expect($import->fresh()->sell_transaction_id)->toBe($sell->id);

    $afterRows = app(TaxReportService::class)->keluaranRows(2026, 7, $data['entity']->id);

    expect($afterRows->pluck('type')->all())->not->toContain('faktur_import');

    $unpostedFakturDpp = TaxFakturImport::query()
        ->where('report_year', 2026)
        ->where('report_month', 7)
        ->where('reporting_entity_id', $data['entity']->id)
        ->whereNull('sell_transaction_id')
        ->sum('dpp');

    expect((float) $unpostedFakturDpp)->toBe(0.0);
});

it('is idempotent and rejects second sell post for same faktur', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    app(PostFakturSell::class)->execute($import->fresh(), [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);
})->throws(InvalidArgumentException::class, 'Sell has already been posted');

it('forbids post sell without report-tax-faktur-import permission', function () {
    $data = seedConsignmentFakturSellScenario();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('report-tax-faktur');

    $import = TaxFakturImport::create([
        'faktur_number' => '04002600888888888',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'seller_name' => 'Seller',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS',
        'buyer_npwp' => '0013179569054000',
        'dpp' => 1_000_000,
        'ppn' => 110_000,
        'report_year' => 2026,
        'report_month' => 7,
        'cash_in_transaction_id' => $data['cashIn']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($viewer)
        ->post(route('reports.tax.faktur.post-sell', $import), [
            'warehouse_id' => $data['warehouse']->id,
            'date_source' => 'faktur',
            'invoice_source' => 'faktur',
            'line_mode' => 'summary',
            'summary_item_id' => $data['item']->id,
        ])
        ->assertForbidden();
});

it('returns clear error when stock is insufficient', function () {
    $data = seedConsignmentFakturSellScenario();
    WarehouseItem::query()
        ->where('warehouse_id', $data['warehouse']->id)
        ->where('item_id', $data['item']->id)
        ->update(['quantity' => 0]);

    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);
})->throws(InvalidArgumentException::class, 'Insufficient stock');

it('posts sell via faktur show form', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $this->post(route('reports.tax.faktur.post-sell', $import), [
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => 'cash_in',
        'invoice_source' => 'cash_in',
        'line_mode' => 'summary',
        'summary_item_id' => $data['item']->id,
    ])
        ->assertRedirect(route('reports.tax.faktur.show', $import))
        ->assertSessionHas('success');

    $sell = Transaction::query()->find($import->fresh()->sell_transaction_id);

    expect($sell)->not->toBeNull()
        ->and($sell->date->toDateString())->toBe('2026-08-15')
        ->and($sell->invoice)->toBe('04002600298450234');
});

it('prefers linked cash-in bank over an earlier cash-in to another entity', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $otherEntity = ReportingEntity::create([
        'name' => 'PT Other',
        'slug' => 'pt-other-sell-entity',
        'is_pkp' => true,
    ]);
    $otherBank = Addrbook::create(['name' => 'Other Entity Bank', 'type' => Addrbook::TYPE_BANK]);
    $otherEntity->banks()->attach($otherBank->id, ['is_active' => true]);

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-01',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $data['customer']->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $otherBank->id,
        'invoice' => 'EARLIER-REMITTANCE',
        'total' => 5_000_000,
        'real_total' => 5_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $data['cashIn']->update(['invoice' => 'MDS-PAY-99']);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $sell = app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'invoice_source' => PostFakturSell::INVOICE_SOURCE_FAKTUR,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);

    $sell = $sell->fresh();
    $entity = app(ReportingSummaryRecorder::class)->resolveEntityForSellTax($sell);

    expect($entity)->not->toBeNull()
        ->and($entity->id)->toBe($data['entity']->id)
        ->and($sell->invoice)->toBe('04002600298450234');

    app(ReportingSummaryRecorder::class)->record($sell);

    $linkedSummary = ReportingMonthlyTaxSummary::query()
        ->where('reporting_entity_id', $data['entity']->id)
        ->where('year', 2026)
        ->where('month', 7)
        ->first();
    $otherSummary = ReportingMonthlyTaxSummary::query()
        ->where('reporting_entity_id', $otherEntity->id)
        ->first();

    expect($linkedSummary)->not->toBeNull()
        ->and((float) $linkedSummary->ppn_keluaran_tax)->toBe(2_334_327.0)
        ->and($otherSummary)->toBeNull();
});

it('posts sell from review save when post_sell is checked', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $this->withSession([
        'tax_faktur_import_preview' => [
            'pdf_path' => null,
            'parsed' => [
                'faktur_number' => $data['parsed']->fakturNumber,
                'faktur_date' => $data['parsed']->fakturDate?->toDateString(),
                'faktur_date_place' => $data['parsed']->fakturDatePlace,
                'seller_name' => $data['parsed']->sellerName,
                'seller_npwp' => $data['parsed']->sellerNpwp,
                'buyer_name' => $data['parsed']->buyerName,
                'buyer_npwp' => $data['parsed']->buyerNpwp,
                'gross_total' => $data['parsed']->grossTotal,
                'discount_total' => $data['parsed']->discountTotal,
                'dpp' => $data['parsed']->dpp,
                'ppn' => $data['parsed']->ppn,
                'ppnbm' => $data['parsed']->ppnbm,
                'signatory_name' => $data['parsed']->signatoryName,
                'source_format' => $data['parsed']->sourceFormat,
                'line_items' => $data['parsed']->lineItems,
            ],
            'suggestion' => [
                'direction' => TaxFakturImport::DIRECTION_KELUARAN,
                'reporting_entity_id' => $data['entity']->id,
            ],
            'counterparty_guess_id' => $data['customer']->id,
            'expected_payment_date' => '2026-08-15',
        ],
    ])->post(route('reports.tax.faktur.store'), [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
        'post_sell' => '1',
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => 'faktur',
        'invoice_source' => 'faktur',
        'line_mode' => 'summary',
        'summary_item_id' => $data['item']->id,
    ])
        ->assertRedirect(route('reports.tax.faktur.show', TaxFakturImport::query()->first()))
        ->assertSessionHas('success');

    $import = TaxFakturImport::query()->where('faktur_number', $data['parsed']->fakturNumber)->first();

    expect($import)->not->toBeNull()
        ->and($import->sell_transaction_id)->not->toBeNull();
});

it('rejects sell date inside a closed book period', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-15'));
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    app(PostFakturSell::class)->execute($import, [
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => PostFakturSell::DATE_SOURCE_FAKTUR,
        'line_mode' => PostFakturSell::LINE_MODE_SUMMARY,
        'summary_item_id' => $data['item']->id,
    ]);
})->throws(InvalidArgumentException::class, 'Tanggal transaksi hanya boleh');

it('ignores empty mapped lines when posting summary sell', function () {
    $data = seedConsignmentFakturSellScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $this->post(route('reports.tax.faktur.post-sell', $import), [
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => 'faktur',
        'invoice_source' => 'faktur',
        'line_mode' => 'summary',
        'summary_item_id' => $data['item']->id,
        'mapped_lines' => [
            ['line_no' => 1, 'item_id' => ''],
            ['line_no' => 2, 'item_id' => ''],
        ],
    ])
        ->assertRedirect(route('reports.tax.faktur.show', $import))
        ->assertSessionHas('success');

    expect($import->fresh()->sell_transaction_id)->not->toBeNull();
});

it('posts mapped sell when every faktur line has an item', function () {
    $data = seedConsignmentFakturSellScenario();
    $itemTwo = Item::factory()->create(['name' => 'Kaos Kaki', 'code' => 'KKS001']);
    WarehouseItem::create([
        'warehouse_id' => $data['warehouse']->id,
        'warehouse_type' => $data['warehouse']->type,
        'item_id' => $itemTwo->id,
        'quantity' => 50,
    ]);

    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600999999999',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta Utara',
        sellerName: 'INDOSPORT ADIGUNA PERKASA',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS RETAILING TBK',
        buyerNpwp: '0013179569054000',
        grossTotal: 3_330_000.0,
        discountTotal: 0,
        dpp: 3_000_000.0,
        ppn: 330_000.0,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
        lineItems: [
            ['line_no' => 1, 'name' => 'Celana Panjang', 'quantity' => 2, 'unit_price' => 1_000_000, 'total' => 2_000_000],
            ['line_no' => 2, 'name' => 'Kaos Kaki', 'quantity' => 10, 'unit_price' => 100_000, 'total' => 1_000_000],
        ],
    );

    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($parsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 3_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $this->post(route('reports.tax.faktur.post-sell', $import), [
        'warehouse_id' => $data['warehouse']->id,
        'date_source' => 'faktur',
        'invoice_source' => 'faktur',
        'line_mode' => 'mapped',
        'mapped_lines' => [
            ['line_no' => 1, 'item_id' => $data['item']->id],
            ['line_no' => 2, 'item_id' => $itemTwo->id],
        ],
    ])
        ->assertRedirect(route('reports.tax.faktur.show', $import))
        ->assertSessionHas('success');

    $sell = Transaction::query()->with('details')->find($import->fresh()->sell_transaction_id);

    expect($sell)->not->toBeNull()
        ->and($sell->details)->toHaveCount(2)
        ->and((float) $sell->total)->toBe(-3_000_000.0);
});

it('returns a clear error when mapped lines are incomplete', function () {
    $data = seedConsignmentFakturSellScenario();
    $itemTwo = Item::factory()->create(['name' => 'Kaos Kaki', 'code' => 'KKS001']);

    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600777777777',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta Utara',
        sellerName: 'INDOSPORT ADIGUNA PERKASA',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS RETAILING TBK',
        buyerNpwp: '0013179569054000',
        grossTotal: 3_330_000.0,
        discountTotal: 0,
        dpp: 3_000_000.0,
        ppn: 330_000.0,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
        lineItems: [
            ['line_no' => 1, 'name' => 'Celana Panjang', 'quantity' => 2, 'unit_price' => 1_000_000, 'total' => 2_000_000],
            ['line_no' => 2, 'name' => 'Kaos Kaki', 'quantity' => 10, 'unit_price' => 100_000, 'total' => 1_000_000],
        ],
    );

    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($parsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 3_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $this->from(route('reports.tax.faktur.show', $import))
        ->post(route('reports.tax.faktur.post-sell', $import), [
            'warehouse_id' => $data['warehouse']->id,
            'date_source' => 'faktur',
            'invoice_source' => 'faktur',
            'line_mode' => 'mapped',
            'mapped_lines' => [
                ['line_no' => 1, 'item_id' => $data['item']->id],
                ['line_no' => 2, 'item_id' => ''],
            ],
        ])
        ->assertRedirect(route('reports.tax.faktur.show', $import))
        ->assertSessionHasErrors('mapped_lines');
});

it('prefills warehouse from customer fulfillment mapping', function () {
    $data = seedConsignmentFakturSellScenario();
    $fulfillmentWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'MDS Fulfillment WH']);
    ReportingWarehouseFulfillment::create([
        'warehouse_id' => $fulfillmentWarehouse->id,
        'customer_id' => $data['customer']->id,
    ]);
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $html = $this->get(route('reports.tax.faktur.show', $import))
        ->assertOk()
        ->assertSee('MDS Fulfillment WH', false)
        ->assertSee('data-testid="faktur-post-sell"', false)
        ->getContent();

    expect($html)->toContain('value="'.$fulfillmentWarehouse->id.'" selected');
});

it('matches faktur lines by pcode and code', function () {
    $item = Item::factory()->create([
        'name' => 'Celana Panjang Navy',
        'code' => 'CLN001',
        'pcode' => 'P-CLN-001',
    ]);

    $matches = app(FakturLineItemMatcher::class)->propose([
        ['line_no' => 1, 'name' => 'P-CLN-001 Extra', 'quantity' => 1, 'unit_price' => 10, 'total' => 10],
    ]);

    expect($matches[0]['best_match'])->not->toBeNull()
        ->and($matches[0]['best_match']['id'])->toBe($item->id)
        ->and($matches[0]['best_match']['score'])->toBeGreaterThanOrEqual(90);
});
