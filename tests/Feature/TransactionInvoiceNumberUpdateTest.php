<?php

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionInvoiceService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create(); // id 1 — superadmin
    $this->user = User::factory()->create();

    Permission::firstOrCreate(['name' => 'transactions-edit-invoice']);
    Permission::firstOrCreate(['name' => 'transactions-show']);
    Permission::firstOrCreate(['name' => 'transactions-list']);
    Permission::firstOrCreate(['name' => 'invoice-maker-list']);
    Permission::firstOrCreate(['name' => 'invoice-maker-edit']);

    $this->user->givePermissionTo(['transactions-edit-invoice', 'transactions-show', 'transactions-list']);

    $this->customer = Addrbook::factory()->customer()->create();
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => 'CASH-OLD',
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $this->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $this->bank->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);
});

it('updates a transaction invoice number', function () {
    $this->actingAs($this->user)
        ->patch(route('transactions.update-invoice', $this->transaction), [
            'invoice' => 'INV/CA/2026/0099',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invoice number updated.');

    expect($this->transaction->fresh()->invoice)->toBe('INV/CA/2026/0099');
});

it('defaults an empty invoice number to the transaction id', function () {
    $this->actingAs($this->user)
        ->patch(route('transactions.update-invoice', $this->transaction), [
            'invoice' => '   ',
        ])
        ->assertRedirect();

    expect($this->transaction->fresh()->invoice)->toBe((string) $this->transaction->id);
});

it('forbids invoice number updates without the dedicated permission', function () {
    Permission::firstOrCreate(['name' => 'transactions-edit']);
    $this->user->syncPermissions(['transactions-show', 'transactions-list', 'transactions-edit']);

    $this->actingAs($this->user)
        ->patch(route('transactions.update-invoice', $this->transaction), [
            'invoice' => 'SHOULD-FAIL',
        ])
        ->assertForbidden();

    expect($this->transaction->fresh()->invoice)->toBe('CASH-OLD');
});

it('hides the invoice edit control without permission', function () {
    $this->user->syncPermissions(['transactions-show', 'transactions-list']);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertOk()
        ->assertDontSee('data-testid="edit-tx-invoice"', false);
});

it('shows the invoice edit control with permission', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertOk()
        ->assertSee('data-testid="edit-tx-invoice"', false);
});

it('rejects invoice numbers longer than the production column', function () {
    $this->actingAs($this->user)
        ->patch(route('transactions.update-invoice', $this->transaction), [
            'invoice' => str_repeat('A', 51),
        ])
        ->assertSessionHasErrors('invoice');

    expect($this->transaction->fresh()->invoice)->toBe('CASH-OLD');
});

it('deletes the cached invoice pdf when the number changes', function () {
    config([
        'core-nation.invoice_path' => storage_path('app/testing-tx-invoice-number/'),
    ]);
    File::ensureDirectoryExists(config('core-nation.invoice_path'));

    $service = app(TransactionInvoiceService::class);
    File::put($service->invoiceDiskPath($service->invoiceFileName($this->transaction)), '%PDF-1.4');
    expect($service->invoicePdfExists($this->transaction))->toBeTrue();

    $this->actingAs($this->user)
        ->patch(route('transactions.update-invoice', $this->transaction), [
            'invoice' => 'NEW-NUM',
        ])
        ->assertRedirect();

    expect($service->invoicePdfExists($this->transaction))->toBeFalse();

    File::deleteDirectory(storage_path('app/testing-tx-invoice-number'));
});

it('shows invoice maker settlement when the number matches', function () {
    $invoice = StandaloneInvoice::factory()->create([
        'number' => 'INV/CA/2026/0444',
        'subtotal' => 2_000_000,
    ]);

    $this->transaction->update(['invoice' => $invoice->number]);
    $this->user->givePermissionTo(['invoice-maker-list', 'invoice-maker-edit']);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertOk()
        ->assertSee('Invoice Maker', false)
        ->assertSee($invoice->number, false)
        ->assertSee('data-testid="invoice-mark-paid"', false);
});
