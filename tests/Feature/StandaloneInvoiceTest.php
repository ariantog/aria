<?php

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\StandaloneInvoiceLine;
use App\Models\User;
use App\Services\InvoiceMakerSettingsService;
use App\Services\StandaloneInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    $response = $this->actingAs($this->user)->post(route('invoice-maker.store'), [
        'number' => 'INV/CA/2026/0001',
        'date' => '2026-08-14',
        'recipient' => "PRASETIA QUBE WELLNESS\nSurabaya",
        'sender_addrbook_id' => $warehouse->id,
        'preset_id' => 'default',
        'lines' => [
            ['description' => 'TECHNO GYM', 'quantity' => 81, 'price' => 100_000],
        ],
    ]);

    $invoice = StandaloneInvoice::first();
    $response->assertRedirect(route('invoice-maker.show', $invoice));

    expect($invoice)->not->toBeNull();
    expect($invoice->recipient)->toBe("PRASETIA QUBE WELLNESS\nSurabaya");
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
    $this->actingAs($this->user)->get(route('invoice-maker.settings.index'))->assertOk()->assertSee('Invoice Maker Settings', false);
    $this->actingAs($this->user)->get(route('invoice-maker.settings.create'))->assertOk()->assertSee('New Preset', false);
});

it('updates an existing invoice preset', function () {
    $service = app(InvoiceMakerSettingsService::class);
    $preset = $service->createPreset([
        'name' => 'Original',
        'terms_of_payment' => 'Terms',
        'pay_to' => "BCA\n123",
        'signatory_name' => 'John',
        'template' => StandaloneInvoice::TEMPLATE_CLASSIC,
    ]);

    $this->actingAs($this->user)
        ->put(route('invoice-maker.settings.update', $preset['id']), [
            'name' => 'Updated Name',
            'terms_of_payment' => 'New terms',
            'pay_to' => "Mandiri\n456",
            'signatory_name' => 'Jane',
            'template' => StandaloneInvoice::TEMPLATE_MODERN,
        ])
        ->assertRedirect(route('invoice-maker.settings.index'))
        ->assertSessionHas('success', 'Invoice preset updated.');

    $updated = $service->findPreset($preset['id']);
    expect($updated['name'])->toBe('Updated Name');
    expect($updated['template'])->toBe(StandaloneInvoice::TEMPLATE_MODERN);
    expect($updated['signatory_name'])->toBe('Jane');
});

it('stores a logo on an invoice preset and snapshots it to invoices', function () {
    File::ensureDirectoryExists(public_path('asset/invoice-logos'));

    $logo = UploadedFile::fake()->image('preset-logo.png', 200, 80);

    $this->actingAs($this->user)
        ->post(route('invoice-maker.settings.store'), [
            'name' => 'Branded',
            'template' => StandaloneInvoice::TEMPLATE_CLASSIC,
            'terms_of_payment' => 'Terms',
            'pay_to' => "BCA\n123",
            'signatory_name' => 'John',
            'logo' => $logo,
        ])
        ->assertRedirect(route('invoice-maker.settings.index'));

    $preset = app(InvoiceMakerSettingsService::class)->findPreset('branded');
    expect($preset)->not->toBeNull();
    expect($preset['logo_path'])->not->toBeNull();
    expect($preset['logo_url'])->not->toBeNull();

    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($this->user)->post(route('invoice-maker.store'), [
        'number' => 'INV/CA/2026/0099',
        'date' => '2026-08-14',
        'recipient' => 'Test Client',
        'sender_addrbook_id' => $warehouse->id,
        'preset_id' => $preset['id'],
        'lines' => [
            ['description' => 'Item', 'quantity' => 1, 'price' => 10_000],
        ],
    ])->assertRedirect();

    $invoice = StandaloneInvoice::where('number', 'INV/CA/2026/0099')->first();
    expect($invoice->logo_path)->toBe($preset['logo_path']);
});

it('keeps preset edit and delete forms separate on the edit page', function () {
    $preset = app(InvoiceMakerSettingsService::class)->defaultPreset();

    $html = $this->actingAs($this->user)
        ->get(route('invoice-maker.settings.edit', $preset['id']))
        ->assertOk()
        ->getContent();

    preg_match('/<form[^>]*action="[^"]*invoice-maker\/settings\/[^"]+"[^>]*method="POST"[^>]*>(.*?)<\/form>/s', $html, $matches);
    expect($matches[1] ?? '')->not->toContain('<form');
    expect($html)->toContain('data-testid="save-preset-button"');
    expect($html)->toContain('data-testid="delete-preset-button"');
});

it('auto-generates invoice numbers with year prefix', function () {
    expect(StandaloneInvoice::generateNumber(new DateTimeImmutable('2026-08-14')))->toBe('INV/CA/2026/0001');

    StandaloneInvoice::factory()->create(['number' => 'INV/CA/2026/0001']);

    expect(StandaloneInvoice::generateNumber(new DateTimeImmutable('2026-08-14')))->toBe('INV/CA/2026/0002');
});
