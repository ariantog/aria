<?php

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\StandaloneInvoiceLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StandaloneInvoiceSettlement;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create(); // id 1 — superadmin

    $this->customer = Addrbook::factory()->customer()->create(['name' => 'PT Bayar']);
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $this->warehouse = Addrbook::factory()->warehouse()->create();

    $this->invoice = StandaloneInvoice::factory()->create([
        'number' => 'INV/CA/2026/0500',
        'subtotal' => 10_000_000,
        'dp_amount' => 2_000_000,
        'discount_amount' => 0,
        'user_id' => $this->user->id,
    ]);
    StandaloneInvoiceLine::factory()->create([
        'standalone_invoice_id' => $this->invoice->id,
        'quantity' => 1,
        'price' => 10_000_000,
        'total' => 10_000_000,
    ]);
});

function cashInFor(string $invoiceNumber, float $amount, int $status = Transaction::STATUS_COMPLETED): Transaction
{
    return Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $invoiceNumber,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => test()->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => test()->bank->id,
        'total' => $amount,
        'real_total' => $amount,
        'status' => $status,
        'user_id' => test()->user->id,
    ]);
}

it('sums multiple cash-in transactions as payments toward one invoice', function () {
    cashInFor($this->invoice->number, 3_000_000);
    cashInFor($this->invoice->number, 2_500_000);
    cashInFor('OTHER-INV', 9_000_000);
    cashInFor($this->invoice->number, 1_000_000, Transaction::STATUS_CANCELLED);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice);

    expect($snapshot['due'])->toBe(8_000_000.0)
        ->and($snapshot['paid_total'])->toBe(5_500_000.0)
        ->and($snapshot['remaining'])->toBe(2_500_000.0)
        ->and($snapshot['status'])->toBe(StandaloneInvoice::STATUS_PARTIAL)
        ->and($snapshot['payments'])->toHaveCount(2);
});

it('treats additional discount as a write-off on the invoice', function () {
    cashInFor($this->invoice->number, 7_400_000);

    $this->actingAs($this->user)
        ->patch(route('invoice-maker.discount', $this->invoice), [
            'discount_amount' => 600_000,
        ])
        ->assertRedirect(route('invoice-maker.show', $this->invoice));

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice->fresh());

    expect((float) $this->invoice->fresh()->discount_amount)->toBe(600_000.0)
        ->and($snapshot['remaining'])->toBe(0.0)
        ->and($snapshot['is_paid'])->toBeFalse();
});

it('marks the invoice paid when payments plus discount cover the balance', function () {
    cashInFor($this->invoice->number, 7_400_000);

    $this->actingAs($this->user)
        ->post(route('invoice-maker.mark-paid', $this->invoice), [
            'discount_amount' => 600_000,
        ])
        ->assertRedirect(route('invoice-maker.show', $this->invoice))
        ->assertSessionHas('success', 'Invoice marked as paid.');

    $invoice = $this->invoice->fresh();
    expect($invoice->isMarkedPaid())->toBeTrue()
        ->and((float) $invoice->discount_amount)->toBe(600_000.0)
        ->and($invoice->paid_by)->toBe($this->user->id);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($invoice);
    expect($snapshot['status'])->toBe(StandaloneInvoice::STATUS_PAID)
        ->and($snapshot['remaining'])->toBe(0.0);
});

it('rejects mark paid when a remainder is still due', function () {
    cashInFor($this->invoice->number, 1_000_000);

    $this->actingAs($this->user)
        ->post(route('invoice-maker.mark-paid', $this->invoice), [
            'discount_amount' => 0,
        ])
        ->assertSessionHasErrors('discount_amount');

    expect($this->invoice->fresh()->isMarkedPaid())->toBeFalse();
});

it('rejects a discount greater than the balance due', function () {
    $this->actingAs($this->user)
        ->patch(route('invoice-maker.discount', $this->invoice), [
            'discount_amount' => 9_000_000,
        ])
        ->assertSessionHasErrors('discount_amount');
});

it('unmarks a paid invoice', function () {
    $this->invoice->update([
        'discount_amount' => 8_000_000,
        'paid_at' => now(),
        'paid_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('invoice-maker.unmark-paid', $this->invoice))
        ->assertRedirect(route('invoice-maker.show', $this->invoice));

    expect($this->invoice->fresh()->isMarkedPaid())->toBeFalse()
        ->and($this->invoice->fresh()->paid_by)->toBeNull()
        ->and((float) $this->invoice->fresh()->discount_amount)->toBe(8_000_000.0);
});

it('lists related non-cash transactions without counting them as payments', function () {
    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $this->invoice->number,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'total' => -10_000_000,
        'real_total' => -10_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice);

    expect($snapshot['paid_total'])->toBe(0.0)
        ->and($snapshot['related'])->toHaveCount(1)
        ->and($snapshot['status'])->toBe(StandaloneInvoice::STATUS_UNPAID);
});

it('forbids mark paid without invoice-maker-edit', function () {
    $staff = User::factory()->create();
    Permission::firstOrCreate(['name' => 'invoice-maker-list']);
    Permission::firstOrCreate(['name' => 'invoice-maker-edit']);
    $staff->givePermissionTo('invoice-maker-list');

    $this->actingAs($staff)
        ->post(route('invoice-maker.mark-paid', $this->invoice), [
            'discount_amount' => 8_000_000,
        ])
        ->assertForbidden();
});

it('shows payment status on the invoice maker index and detail', function () {
    cashInFor($this->invoice->number, 1_000_000);

    $this->actingAs($this->user)
        ->get(route('invoice-maker.index'))
        ->assertOk()
        ->assertSee('Partial', false);

    $this->actingAs($this->user)
        ->get(route('invoice-maker.show', $this->invoice))
        ->assertOk()
        ->assertSee('Payment', false)
        ->assertSee('Linked cash-in', false)
        ->assertSee('data-testid="invoice-mark-paid"', false);
});
