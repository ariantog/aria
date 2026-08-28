<?php

use App\Models\Addrbook;
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

it('generates the receipt pdf and serves it inline', function () {
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'Store - CITOS OFFLINE']);
    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'invoice' => '615922',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'real_total' => -934_700,
        'total_items' => 4,
    ]);
    $item = Item::factory()->create(['name' => 'LANA TOP - LILAC', 'code' => 'AJDCA2302510L']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 299_900,
        'discount' => 20,
        'total' => 239_920,
    ]);

    $service = app(TransactionInvoiceService::class);
    $fileName = $service->receiptFileName($transaction);

    $this->actingAs($this->user)
        ->get(route('transactions.receipt', $transaction))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(File::exists($service->invoiceDiskPath($fileName)))->toBeTrue();
});

it('renders the receipt pdf blade with item name and code', function () {
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'Store - CITOS OFFLINE']);
    $transaction = Transaction::factory()->create([
        'invoice' => '615922',
        'sender_id' => $sender->id,
        'real_total' => -934_700,
        'total_items' => 4,
    ]);
    $item = Item::factory()->create(['name' => 'LANA TOP - LILAC', 'code' => 'AJDCA2302510L']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 299_900,
        'discount' => 20,
        'total' => 239_920,
    ]);
    $transaction->load(['details.item', 'sender']);
    $branding = app(\App\Services\InvoiceBrandingService::class)->forTransaction($transaction);

    $html = view('transactions.pdf.receipt', compact('transaction', 'branding'))->render();

    expect($html)
        ->toContain('CoreNation Active')
        ->toContain('615922')
        ->toContain('Store - CITOS OFFLINE')
        ->toContain('LANA TOP - LILAC')
        ->toContain('AJDCA2302510L')
        ->toContain('Grand Total')
        ->toContain('Rp934.700');
});

it('renders the dot matrix print page with item view columns', function () {
    $item = Item::factory()->create(['name' => 'Printed Shirt', 'code' => 'SKU-PRINT-01']);
    $transaction = Transaction::factory()->create(['invoice' => 'PRT-001']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 50_000,
        'discount' => 10,
        'total' => 90_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.print', [
            'transaction' => $transaction,
            'image' => 0,
            'barcode' => 1,
            'sku' => 1,
            'name' => 1,
        ]))
        ->assertOk()
        ->assertSee('Printed Shirt', false)
        ->assertSee('SKU-PRINT-01', false)
        ->assertSee('Disc(%)', false)
        ->assertSee('css/print.css', false);
});

it('generates invoice pdf with item view columns from request', function () {
    $item = Item::factory()->create(['name' => 'PDF Shirt', 'code' => 'SKU-PDF-01']);
    $transaction = Transaction::factory()->create([
        'invoice' => 'PDF-COLS',
        'real_total' => 90_000,
        'total' => 90_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100_000,
        'discount' => 10,
        'total' => 90_000,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.pdf.store', $transaction), [
            'image' => 0,
            'barcode' => 0,
            'sku' => 1,
            'name' => 1,
        ])
        ->assertRedirect(route('transactions.show', $transaction));

    $html = view('transactions.pdf.invoice', [
        'transaction' => $transaction->load('details.item'),
        'typeLabel' => $transaction->getTypeLabel(),
        'branding' => app(\App\Services\InvoiceBrandingService::class)->forTransaction($transaction),
        'itemView' => \App\Support\TransactionItemViewOptions::fromRequest(new \Illuminate\Http\Request([
            'image' => 0,
            'barcode' => 0,
            'sku' => 1,
            'name' => 1,
        ])),
    ])->render();

    expect($html)->toContain('SKU-PDF-01')->toContain('PDF Shirt');
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
