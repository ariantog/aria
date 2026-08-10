<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\InvoiceBrandingService;
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

it('parses invoice header from addrbook description', function () {
    $service = app(InvoiceBrandingService::class);

    expect($service->parseDescriptionHeader("FX Store\nJl. Sudirman No. 1\nJakarta"))->toBe([
        'company_name' => 'FX Store',
        'address' => "Jl. Sudirman No. 1\nJakarta",
    ]);
});

it('uses sender description and phone for transaction branding', function () {
    $sender = Addrbook::factory()->warehouse()->create([
        'name' => 'Warehouse Name',
        'description' => "Maggio Store\nCilandak Town Square no.171",
        'phone' => '08111222333',
        'address' => 'Ignored when description is set',
    ]);
    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $branding = app(InvoiceBrandingService::class)->forTransaction($transaction);

    expect($branding['company_name'])->toBe('Maggio Store');
    expect($branding['address'])->toBe('Cilandak Town Square no.171');
    expect($branding['phone'])->toBe('08111222333');
});

it('falls back to sender name and address when description is empty', function () {
    $sender = Addrbook::factory()->warehouse()->create([
        'name' => 'Fallback Store',
        'description' => null,
        'address' => "Jl. Contoh 5\nBandung",
        'phone' => '08199887766',
    ]);
    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $branding = app(InvoiceBrandingService::class)->forTransaction($transaction);

    expect($branding['company_name'])->toBe('Fallback Store');
    expect($branding['address'])->toBe("Jl. Contoh 5\nBandung");
    expect($branding['phone'])->toBe('08199887766');
});

it('renders sender branding on receipt and print pages', function () {
    $sender = Addrbook::factory()->warehouse()->create([
        'description' => "Receipt Store\nJl. Receipt 99",
        'phone' => '08123456789',
    ]);
    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'invoice_number' => 'RCP-BRAND',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    $item = Item::factory()->create(['name' => 'Branded Shirt']);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 50_000,
        'total' => 50_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.receipt', $transaction))
        ->assertOk()
        ->assertSee('Receipt Store', false)
        ->assertSee('Jl. Receipt 99', false)
        ->assertSee('08123456789', false)
        ->assertDontSee('CORENATION', false);

    $this->actingAs($this->user)
        ->get(route('transactions.print', $transaction))
        ->assertOk()
        ->assertSee('Receipt Store', false)
        ->assertSee('Jl. Receipt 99', false)
        ->assertSee('08123456789', false);
});

it('generates pdf with sender branding', function () {
    $sender = Addrbook::factory()->warehouse()->create([
        'description' => "PDF Store\nJl. PDF 10",
        'phone' => '08111111111',
    ]);
    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'grand_total' => 25_000,
        'total' => 25_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'quantity' => 1,
        'price' => 25_000,
        'total' => 25_000,
    ]);

    app(TransactionInvoiceService::class)->createInvoicePdf($transaction);

    $filePath = app(TransactionInvoiceService::class)->invoiceDiskPath('invoice_'.$transaction->id.'.pdf');
    expect(File::exists($filePath))->toBeTrue();
});
