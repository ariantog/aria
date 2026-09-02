<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\ReportingSummaryRecorder;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\FakturSellMatcher;
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

function seedFakturLinkScenario(): array
{
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-link-sells',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $bank = Addrbook::create(['name' => 'BCA Link', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Link']);
    $customer = Addrbook::factory()->customer()->create([
        'name' => 'MDS RETAILING TBK',
        'payment_due_day' => 15,
        'payment_grace_days' => 7,
    ]);

    $cashIn = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'invoice' => 'MDS-PAY-LINK',
        'total' => 20_000_000,
        'real_total' => 20_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $sellA = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-07-20',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'INV-A',
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 10_000_000),
        'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, 11_100_000),
        'ppn' => 1_100_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $cashIn->user_id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $sellB = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-07-28',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'INV-B',
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 9_452_728),
        'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, 10_687_055),
        'ppn' => 1_234_327,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $cashIn->user_id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600999990001',
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
        lineItems: [],
    );

    return compact('entity', 'bank', 'warehouse', 'customer', 'cashIn', 'sellA', 'sellB', 'parsed');
}

it('links multiple existing sells to one faktur without creating a new sell', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
        'cash_in_transaction_id' => $data['cashIn']->id,
    ]);

    $sellCountBefore = Transaction::query()->where('type', Transaction::TYPE_SELL)->count();

    app(LinkFakturSells::class)->attach($import, [$data['sellA']->id, $data['sellB']->id]);

    $import = $import->fresh(['sellTransactions']);

    expect($import->sellTransactions)->toHaveCount(2)
        ->and($import->hasLinkedSells())->toBeTrue()
        ->and($import->linkedSellDpp())->toBe(19_452_728.0)
        ->and((int) $import->sell_transaction_id)->toBe($data['sellA']->id)
        ->and(Transaction::query()->where('type', Transaction::TYPE_SELL)->count())->toBe($sellCountBefore);
});

it('rejects a sell already linked to another faktur', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $first = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'cash_in_transaction_id' => $data['cashIn']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
    ]);
    app(LinkFakturSells::class)->attach($first, [$data['sellA']->id]);

    $otherParsed = new ParsedFakturPajak(
        fakturNumber: '04002600999990002',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta',
        sellerName: 'INDOSPORT ADIGUNA PERKASA',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS RETAILING TBK',
        buyerNpwp: '0013179569054000',
        grossTotal: 1_110_000,
        discountTotal: 0,
        dpp: 1_000_000,
        ppn: 110_000,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
    );

    $second = app(TaxFakturImportService::class)->storeFromParsed($otherParsed, [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
    ]);

    app(LinkFakturSells::class)->attach($second, [$data['sellA']->id]);
})->throws(InvalidArgumentException::class, 'already linked to another faktur');

it('does not add remaining faktur dpp to ppn ringkasan after a partial sell link', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    ReportingMonthlyTaxSummary::create([
        'year' => 2026,
        'month' => 7,
        'reporting_entity_id' => $data['entity']->id,
        'ppn_keluaran_dpp' => 10_000_000,
        'ppn_keluaran_tax' => 1_100_000,
    ]);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
    ]);

    expect(app(TaxReportService::class)->ringkasan(2026, 7, $data['entity']->id)['keluaran_dpp'])
        ->toBe(29_452_728.0);

    app(LinkFakturSells::class)->attach($import, [$data['sellA']->id]);

    $ringkasan = app(TaxReportService::class)->ringkasan(2026, 7, $data['entity']->id);

    expect($import->fresh(['sellTransactions'])->remainingSellDpp())->toBe(9_452_728.0)
        ->and($ringkasan['keluaran_dpp'])->toBe(10_000_000.0)
        ->and($ringkasan['keluaran_tax'])->toBe(1_100_000.0);
});

it('excludes linked faktur from keluaran drill-down', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
    ]);

    expect(app(TaxReportService::class)->keluaranRows(2026, 7, $data['entity']->id)->pluck('type')->all())
        ->toContain('faktur_import');

    app(LinkFakturSells::class)->attach($import, [$data['sellA']->id]);

    expect(app(TaxReportService::class)->keluaranRows(2026, 7, $data['entity']->id)->pluck('type')->all())
        ->not->toContain('faktur_import');
});

it('resolves sell tax entity from linked cash in via pivot', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
        'cash_in_transaction_id' => $data['cashIn']->id,
        'payment_received_amount' => 20_000_000,
        'payment_received_date' => '2026-08-15',
    ]);

    app(LinkFakturSells::class)->attach($import, [$data['sellB']->id]);

    $entity = app(ReportingSummaryRecorder::class)->resolveEntityForSellTax($data['sellB']->fresh());

    expect($entity)->not->toBeNull()
        ->and($entity->id)->toBe($data['entity']->id);
});

it('suggests existing customer sells for the faktur window', function () {
    $data = seedFakturLinkScenario();

    $suggestions = app(FakturSellMatcher::class)->suggest(
        $data['customer']->id,
        '2026-07-31',
        '04002600999990001',
        19_452_728.0,
    );

    expect($suggestions->pluck('id')->all())->toContain($data['sellA']->id, $data['sellB']->id);
});

it('links sells from the faktur show form', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
    ]);

    $this->post(route('reports.tax.faktur.link-sells', $import), [
        'sell_transaction_ids' => [$data['sellA']->id, $data['sellB']->id],
    ])
        ->assertRedirect(route('reports.tax.faktur.show', $import))
        ->assertSessionHas('success');

    expect($import->fresh()->sellTransactions)->toHaveCount(2);

    $this->get(route('reports.tax.faktur.show', $import))
        ->assertOk()
        ->assertSee('INV-A', false)
        ->assertSee('INV-B', false)
        ->assertSee('data-testid="faktur-link-sells"', false);
});

it('unlinks a sell and restores faktur in drill-down when none remain', function () {
    $data = seedFakturLinkScenario();
    $this->actingAs($this->user);

    $import = app(TaxFakturImportService::class)->storeFromParsed($data['parsed'], [
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $data['entity']->id,
        'counterparty_id' => $data['customer']->id,
    ]);
    app(LinkFakturSells::class)->attach($import, [$data['sellA']->id]);

    $this->delete(route('reports.tax.faktur.unlink-sell', [$import, $data['sellA']->id]))
        ->assertRedirect(route('reports.tax.faktur.show', $import));

    $import = $import->fresh();
    expect($import->hasLinkedSells())->toBeFalse()
        ->and($import->sell_transaction_id)->toBeNull();

    expect(app(TaxReportService::class)->keluaranRows(2026, 7, $data['entity']->id)->pluck('type')->all())
        ->toContain('faktur_import');
});

it('forbids linking sells without report-tax-faktur-import permission', function () {
    $data = seedFakturLinkScenario();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('report-tax-faktur');

    $import = TaxFakturImport::create([
        'faktur_number' => '04002600999990009',
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
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($viewer)
        ->post(route('reports.tax.faktur.link-sells', $import), [
            'sell_transaction_ids' => [$data['sellA']->id],
        ])
        ->assertForbidden();
});
