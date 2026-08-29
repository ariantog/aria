<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\TransactionInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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
        'invoice' => 'RCP-001',
        'real_total' => 100_000,
        'total' => 100_000,
    ]);
    $item = Item::factory()->create(['name' => 'Test Shirt', 'code' => 'AJDCA2302510L']);
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
        ->assertSee('FX SUDIRMAN', false)
        ->assertSee('MAGGIORE GRANDE', false)
        ->assertSee('Test Shirt', false)
        ->assertSee('AJDCA2302510L', false)
        ->assertSee('css/receipt.css', false);
});

it('renders the dot matrix print page', function () {
    $transaction = Transaction::factory()->create(['invoice' => 'PRT-001']);
    TransactionDetail::factory()->create(['transaction_id' => $transaction->id]);

    $this->actingAs($this->user)
        ->get(route('transactions.print', $transaction))
        ->assertOk()
        ->assertSee('PRT-001', false)
        ->assertSee('css/print.css', false);
});

it('shows save to pdf on transaction detail when no pdf exists', function () {
    $transaction = Transaction::factory()->create(['invoice' => 'PDF-NEW']);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Save to PDF', false)
        ->assertDontSee('View PDF', false);
});

it('shows view pdf on transaction detail when pdf already exists', function () {
    $transaction = Transaction::factory()->create(['invoice' => 'PDF-EXISTS']);
    $filePath = app(TransactionInvoiceService::class)->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    File::put($filePath, '%PDF-1.4 fake');

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('View PDF', false)
        ->assertSee('Regenerate PDF', false)
        ->assertSee(route('transactions.pdf.show', $transaction), false)
        ->assertDontSee('Save to PDF', false);
});

it('serves invoice pdf via app route', function () {
    $transaction = Transaction::factory()->create(['invoice' => 'PDF-SERVE']);
    $filePath = app(TransactionInvoiceService::class)->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    File::put($filePath, '%PDF-1.4 fake');

    $this->actingAs($this->user)
        ->get(route('transactions.pdf.show', $transaction))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('regenerates an existing invoice pdf', function () {
    $transaction = Transaction::factory()->create([
        'invoice' => 'PDF-REGEN',
        'real_total' => 25_000,
        'total' => 25_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'quantity' => 1,
        'price' => 25_000,
        'total' => 25_000,
    ]);
    $service = app(TransactionInvoiceService::class);
    $service->createInvoicePdf($transaction);
    $filePath = $service->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    $mtimeBefore = File::lastModified($filePath);

    sleep(1);

    $this->actingAs($this->user)
        ->post(route('transactions.pdf.store', $transaction))
        ->assertRedirect(route('transactions.show', $transaction))
        ->assertSessionHas('success', 'Invoice PDF regenerated.');

    expect(File::lastModified($filePath))->toBeGreaterThan($mtimeBefore);
});

it('creates invoice pdf via save to pdf and shows view pdf', function () {
    $transaction = Transaction::factory()->create([
        'invoice' => 'PDF-SAVE',
        'real_total' => 25_000,
        'total' => 25_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'quantity' => 1,
        'price' => 25_000,
        'total' => 25_000,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.pdf.store', $transaction))
        ->assertRedirect(route('transactions.show', $transaction));

    $filePath = app(TransactionInvoiceService::class)->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    expect(File::exists($filePath))->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('View PDF', false)
        ->assertDontSee('Save to PDF', false);
});

it('generates invoice pdf and redirects to whatsapp with cdn link', function () {
    $transaction = Transaction::factory()->create([
        'invoice' => 'WA-001',
        'real_total' => 50_000,
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

it('reuses existing pdf for whatsapp without regenerating', function () {
    $transaction = Transaction::factory()->create(['invoice' => 'WA-EXIST']);
    $service = app(TransactionInvoiceService::class);
    $filePath = $service->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    File::put($filePath, '%PDF-1.4 existing');
    $mtimeBefore = File::lastModified($filePath);

    sleep(1);

    $response = $this->actingAs($this->user)->post(route('transactions.whatsapp', $transaction), [
        'phone' => '62812244226656',
    ]);

    $response->assertRedirect();
    expect(File::lastModified($filePath))->toBe($mtimeBefore);
});

it('paginates the transactions index with per_page 100 by default', function () {
    Transaction::factory()->count(105)->create();

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('value="100"', false);
});

it('exports the current transactions page to excel', function () {
    Transaction::factory()->count(3)->create(['invoice' => 'EXP-001']);

    $response = $this->actingAs($this->user)->get(route('transactions.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('shows group alias as item name on transaction detail', function () {
    if (! Schema::hasColumn('item_group', 'alias')) {
        Schema::table('item_group', function ($table) {
            $table->string('alias')->nullable();
        });
    }

    $group = ItemGroup::factory()->create(['name' => 'GROUP PRODUCT NAME']);
    DB::table('item_group')->where('id', $group->id)->update(['alias' => 'GROUP ALIAS NAME']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'ITEM DISPLAY NAME - NAVY - S',
        'code' => 'AJD-TX-SHOW-S',
    ]);

    $transaction = Transaction::factory()->create(['invoice' => 'TX-ALIAS-NAME']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('GROUP ALIAS NAME', false)
        ->assertDontSee('ITEM DISPLAY NAME - NAVY - S', false);
});

it('shows item description toggle and column on transaction detail', function () {
    $item = Item::factory()->create([
        'name' => 'Desc Toggle Item',
        'code' => 'AJD-DESC-TOGGLE-M',
        'description' => 'ITEM DESCRIPTION FOR TX SHOW',
    ]);

    $transaction = Transaction::factory()->create(['invoice' => 'TX-DESC-COL']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('x-model="showDescription"', false)
        ->assertSee('ITEM DESCRIPTION FOR TX SHOW', false)
        ->assertSee('data-copy-col="desc"', false);
});
