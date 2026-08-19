<?php

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\StandaloneInvoiceLine;
use App\Models\User;
use App\Services\InvoiceMakerSettingsService;
use App\Services\StandaloneInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    config([
        'core-nation.invoice_path' => storage_path('app/testing-standalone-invoices/'),
        'core-nation.invoice_url' => 'https://invoice.test/',
    ]);
    File::ensureDirectoryExists(config('core-nation.invoice_path'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/testing-standalone-invoices'));
});

it('parses pay to lines into bank account details', function () {
    $service = app(InvoiceMakerSettingsService::class);

    expect($service->parsePayTo("BCA\n5105251588\nCV ACTIVEWEAR"))->toBe([
        'bank' => 'BCA',
        'account_number' => '5105251588',
        'account_name' => 'CV ACTIVEWEAR',
    ]);
});

it('converts terms of payment lines into bullets', function () {
    $service = app(InvoiceMakerSettingsService::class);

    expect($service->termsBullets("Line one\n\nLine two"))->toBe(['Line one', 'Line two']);
});

it('creates a standalone invoice with free-text lines', function () {
    $warehouse = Addrbook::factory()->warehouse()->create([
        'description' => "Core Store\nJl. Test 1",
    ]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'PRASETIA QUBE WELLNESS']);

    $response = $this->actingAs($this->user)->post(route('invoice-maker.store'), [
        'number' => 'INV/CA/2026/0001',
        'date' => '2026-08-14',
        'recipient_name' => $customer->name,
        'recipient_addrbook_id' => $customer->id,
        'sender_addrbook_id' => $warehouse->id,
        'template' => StandaloneInvoice::TEMPLATE_CLASSIC,
        'terms_of_payment' => "Pembayaran lunas.\nHarga belum termasuk PPN.",
        'pay_to' => "BCA\n5105251588\nCV ACTIVEWEAR GLOBAL MANDIRI",
        'signatory_name' => 'Arianto Gunawan',
        'lines' => [
            ['description' => 'TECHNO GYM', 'quantity' => 81, 'price' => 100_000],
        ],
    ]);

    $invoice = StandaloneInvoice::first();
    $response->assertRedirect(route('invoice-maker.show', $invoice));

    expect($invoice)->not->toBeNull();
    expect($invoice->recipient_name)->toBe('PRASETIA QUBE WELLNESS');
    expect($invoice->lines)->toHaveCount(1);
    expect((float) $invoice->subtotal)->toBe(8_100_000.0);
    expect((float) $invoice->total_qty)->toBe(81.0);
});

it('generates and regenerates standalone invoice pdf', function () {
    $invoice = StandaloneInvoice::factory()->create([
        'number' => 'INV/CA/2026/0001',
        'template' => StandaloneInvoice::TEMPLATE_CLASSIC,
    ]);
    StandaloneInvoiceLine::factory()->create([
        'standalone_invoice_id' => $invoice->id,
        'description' => 'TECHNO GYM',
        'quantity' => 2,
        'price' => 50_000,
        'total' => 100_000,
    ]);

    $service = app(StandaloneInvoiceService::class);
    expect($service->invoicePdfExists($invoice))->toBeFalse();

    $this->actingAs($this->user)
        ->post(route('invoice-maker.pdf.store', $invoice))
        ->assertRedirect(route('invoice-maker.show', $invoice));

    expect($service->invoicePdfExists($invoice))->toBeTrue();

    $this->actingAs($this->user)
        ->post(route('invoice-maker.pdf.store', $invoice))
        ->assertRedirect(route('invoice-maker.show', $invoice))
        ->assertSessionHas('success', 'Invoice PDF regenerated.');

    $this->actingAs($this->user)
        ->get(route('invoice-maker.pdf.download', $invoice))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=INV-CA-2026-0001.pdf');
});

it('renders invoice maker pages for superadmin', function () {
    $invoice = StandaloneInvoice::factory()->create();
    StandaloneInvoiceLine::factory()->create(['standalone_invoice_id' => $invoice->id]);

    $this->actingAs($this->user)->get(route('invoice-maker.index'))->assertOk()->assertSee('Invoice Maker', false);
    $this->actingAs($this->user)->get(route('invoice-maker.create'))->assertOk()->assertSee('New Invoice', false);
    $this->actingAs($this->user)->get(route('invoice-maker.show', $invoice))->assertOk()->assertSee($invoice->number, false);
    $this->actingAs($this->user)->get(route('invoice-maker.edit', $invoice))->assertOk()->assertSee('Edit Invoice', false);
    $this->actingAs($this->user)->get(route('invoice-settings.edit'))->assertOk()->assertSee('Terms of Payment', false);
});

it('auto-generates invoice numbers with year prefix', function () {
    expect(StandaloneInvoice::generateNumber(new DateTimeImmutable('2026-08-14')))->toBe('INV/CA/2026/0001');

    StandaloneInvoice::factory()->create(['number' => 'INV/CA/2026/0001']);

    expect(StandaloneInvoice::generateNumber(new DateTimeImmutable('2026-08-14')))->toBe('INV/CA/2026/0002');
});
