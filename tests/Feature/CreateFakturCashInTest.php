<?php

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
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

function seedFakturCashInScenario(): array
{
    $entity = ReportingEntity::create([
        'name' => 'PT Indosport',
        'slug' => 'pt-indosport-faktur-cash-in',
        'is_pkp' => true,
        'npwp' => '0504330085044000',
    ]);
    $bank = Addrbook::create(['name' => 'BCA Faktur Cash In', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'MDS RETAILING TBK']);

    $import = TaxFakturImport::create([
        'faktur_number' => '04002600298450234',
        'faktur_date' => '2026-07-31',
        'direction' => TaxFakturImport::DIRECTION_KELUARAN,
        'reporting_entity_id' => $entity->id,
        'counterparty_id' => $customer->id,
        'seller_name' => 'INDOSPORT',
        'seller_npwp' => '0504330085044000',
        'buyer_name' => 'MDS RETAILING TBK',
        'buyer_npwp' => '0013179569054000',
        'gross_total' => 21_221_157,
        'discount_total' => 0,
        'down_payment_total' => 0,
        'dpp' => 19_452_728,
        'ppn' => 2_334_327,
        'ppnbm' => 0,
        'report_year' => 2026,
        'report_month' => 7,
        'user_id' => test()->user->id,
    ]);

    return compact('entity', 'bank', 'customer', 'import');
}

it('shows the create cash in form on a keluaran faktur without a linked cash in', function () {
    $data = seedFakturCashInScenario();

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.show', $data['import']))
        ->assertOk()
        ->assertSee('data-testid="faktur-create-cash-in"', false)
        ->assertSee('data-testid="faktur-create-cash-in-submit"', false)
        ->assertSee((string) $data['bank']->id, false);
});

it('creates a cash in from the faktur and links it automatically', function () {
    $data = seedFakturCashInScenario();
    $amount = $data['import']->fakturGross();

    $this->actingAs($this->user)
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']), [
            'amount' => $amount,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-15',
        ])
        ->assertRedirect(route('reports.tax.faktur.show', $data['import']));

    $import = $data['import']->fresh();
    expect($import->cash_in_transaction_id)->not->toBeNull()
        ->and((float) $import->payment_received_amount)->toBe($amount)
        ->and($import->payment_received_date?->toDateString())->toBe('2026-08-15')
        ->and((float) $import->payment_variance)->toBe(0.0);

    $cashIn = Transaction::query()->find($import->cash_in_transaction_id);
    expect($cashIn)->not->toBeNull()
        ->and((int) $cashIn->type)->toBe(Transaction::TYPE_CASH_IN)
        ->and((int) $cashIn->sender_id)->toBe($data['customer']->id)
        ->and((int) $cashIn->receiver_id)->toBe($data['bank']->id)
        ->and($cashIn->invoice)->toBe('04002600298450234')
        ->and((float) $cashIn->ppn)->toBe(0.0)
        ->and($cashIn->ppn_dpp)->toBeNull()
        ->and((float) $cashIn->total)->toBe($amount);
});

it('hides the create form after a cash in is linked', function () {
    $data = seedFakturCashInScenario();
    $this->actingAs($this->user)
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']), [
            'amount' => 1_000_000,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-15',
        ]);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.show', $data['import']->fresh()))
        ->assertOk()
        ->assertDontSee('data-testid="faktur-create-cash-in-submit"', false);
});

it('rejects a second cash in create for the same faktur', function () {
    $data = seedFakturCashInScenario();
    $this->actingAs($this->user)
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']), [
            'amount' => 1_000_000,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-15',
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->from(route('reports.tax.faktur.show', $data['import']))
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']->fresh()), [
            'amount' => 500_000,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-16',
        ])
        ->assertRedirect(route('reports.tax.faktur.show', $data['import']));

    expect(Transaction::query()->where('type', Transaction::TYPE_CASH_IN)->count())->toBe(1);
});

it('does not offer create cash in on masukan faktur', function () {
    $data = seedFakturCashInScenario();
    $data['import']->update(['direction' => TaxFakturImport::DIRECTION_MASUKAN]);

    $this->actingAs($this->user)
        ->get(route('reports.tax.faktur.show', $data['import']->fresh()))
        ->assertOk()
        ->assertDontSee('data-testid="faktur-create-cash-in-submit"', false);

    $this->actingAs($this->user)
        ->from(route('reports.tax.faktur.show', $data['import']))
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']->fresh()), [
            'amount' => 1_000_000,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-15',
        ])
        ->assertRedirect();

    expect($data['import']->fresh()->cash_in_transaction_id)->toBeNull();
});

it('forbids creating cash in without import or cash-in permission', function () {
    $data = seedFakturCashInScenario();
    $viewer = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    app(PermissionGenerator::class)->generateForModule('Transaction');
    $viewer->givePermissionTo('report-tax-faktur');

    $this->actingAs($viewer)
        ->post(route('reports.tax.faktur.cash-in.store', $data['import']), [
            'amount' => 1_000_000,
            'account_id' => $data['bank']->id,
            'date' => '2026-08-15',
        ])
        ->assertForbidden();
});
