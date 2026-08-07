<?php

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\TransactionInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    config([
        'core-nation.invoice_path' => storage_path('app/testing-invoices/'),
        'core-nation.invoice_url' => 'https://invoice.test/',
    ]);
    File::ensureDirectoryExists(config('core-nation.invoice_path'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/testing-invoices'));
});

it('renders the thermal receipt page for print pos', function () {
    $transaction = Transaction::factory()->create([
        'invoice_number' => 'RCP-001',
        'grand_total' => 100_000,
        'total' => 100_000,
    ]);
    $item = Item::factory()->create(['name' => 'Test Shirt']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 50_000,
        'total' => 100_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.receipt', $transaction))
        ->assertOk()
        ->assertSee('CORENATION', false)
        ->assertSee('CILANDAK TOWN SQUARE', false)
        ->assertSee('Test Shirt', false)
        ->assertDontSee('FX SUDIRMAN', false)
        ->assertDontSee('MAGGIORE GRANDE', false);
});

it('renders the dot matrix print page', function () {
    $transaction = Transaction::factory()->create(['invoice_number' => 'PRT-001']);
    TransactionDetail::factory()->create(['transaction_id' => $transaction->id]);

    $this->actingAs($this->user)
        ->get(route('transactions.print', $transaction))
        ->assertOk()
        ->assertSee('PRT-001', false)
        ->assertSee('css/print.css', false);
});

it('generates invoice pdf and redirects to whatsapp with cdn link', function () {
    $transaction = Transaction::factory()->create([
        'invoice_number' => 'WA-001',
        'grand_total' => 50_000,
        'total' => 50_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'quantity' => 1,
        'price' => 50_000,
        'total' => 50_000,
    ]);

    $response = $this->actingAs($this->user)->post(route('transactions.whatsapp', $transaction), [
        'phone' => '62812244226656',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('https://wa.me/62812244226656')
        ->toContain(urlencode('https://invoice.test/invoice_'.$transaction->id.'.pdf'));

    expect(File::exists(app(TransactionInvoiceService::class)->invoiceDiskPath('invoice_'.$transaction->id.'.pdf')))->toBeTrue();
});

it('paginates the transactions index with per_page 100 by default', function () {
    Transaction::factory()->count(105)->create();

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('value="100"', false);
});

it('exports the current transactions page to excel', function () {
    Transaction::factory()->count(3)->create(['invoice_number' => 'EXP-001']);

    $response = $this->actingAs($this->user)->get(route('transactions.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
