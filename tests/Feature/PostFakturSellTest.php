<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\Reporting\ReportingSummaryRecorder;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\PostFakturSell;
use App\Services\Tax\TaxFakturImportService;
use App\Services\Tax\ParsedFakturPajak;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use App\Jobs\UpdateTransactionSummaries;

beforeEach(function () {
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
    ] as $path) {
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
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
        grossTotal: 21_787_055.0,
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
        ->and((float) $sell->real_total)->toBe(-21_787_055.0)
        ->and($sell->invoice)->toBe('04002600298450234')
        ->and($sell->date->toDateString())->toBe('2026-07-31');

    $stock = WarehouseItem::query()
        ->where('warehouse_id', $data['warehouse']->id)
        ->where('item_id', $data['item']->id)
        ->value('quantity');

    expect((float) $stock)->toBe(99.0);
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

    expect(abs((float) $sell->real_total))->toBe($fakturGross)
        ->and(abs((float) $sell->real_total))->toBeGreaterThan($paymentReceived);
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
