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
    $this->otherCustomer = Addrbook::factory()->customer()->create(['name' => 'PT Lain']);
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $this->otherBank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $this->warehouse = Addrbook::factory()->warehouse()->create();
    $this->otherWarehouse = Addrbook::factory()->warehouse()->create();

    $this->invoice = StandaloneInvoice::factory()->create([
        'number' => 'INV/CA/2026/0500',
        'subtotal' => 10_000_000,
        'dp_amount' => null,
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

function cashInFor(string $invoiceNumber, float $amount, int $status = Transaction::STATUS_COMPLETED, ?int $senderId = null, ?int $receiverId = null): Transaction
{
    return Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $invoiceNumber,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $senderId ?? test()->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $receiverId ?? test()->bank->id,
        'total' => $amount,
        'real_total' => $amount,
        'status' => $status,
        'user_id' => test()->user->id,
    ]);
}

function sellFor(string $invoiceNumber, float $amount, ?int $senderId = null, ?int $receiverId = null): Transaction
{
    return Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $invoiceNumber,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $senderId ?? test()->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $receiverId ?? test()->customer->id,
        'total' => -abs($amount),
        'real_total' => -abs($amount),
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
    ]);
}

it('sums multiple cash-in and sell transactions toward one invoice', function () {
    cashInFor($this->invoice->number, 3_000_000);
    cashInFor($this->invoice->number, 2_500_000, Transaction::STATUS_COMPLETED, test()->otherCustomer->id, test()->otherBank->id);
    cashInFor('OTHER-INV', 9_000_000);
    cashInFor($this->invoice->number, 1_000_000, Transaction::STATUS_CANCELLED);
    sellFor($this->invoice->number, 4_000_000);
    sellFor($this->invoice->number, 1_500_000, test()->otherWarehouse->id, test()->otherCustomer->id);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_TRANSFER,
        'invoice' => $this->invoice->number,
        'total' => -5_000_000,
        'real_total' => -5_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice);

    expect($snapshot['invoice_amount'])->toBe(10_000_000.0)
        ->and($snapshot['paid_total'])->toBe(5_500_000.0)
        ->and($snapshot['sell_total'])->toBe(5_500_000.0)
        ->and($snapshot['status'])->toBe(StandaloneInvoice::STATUS_PARTIAL)
        ->and($snapshot['payments'])->toHaveCount(2)
        ->and($snapshot['sells'])->toHaveCount(2)
        ->and($snapshot['is_paid'])->toBeFalse();
});

it('marks the invoice paid when invoice, sell, and cash-in totals match', function () {
    cashInFor($this->invoice->number, 6_000_000);
    cashInFor($this->invoice->number, 4_000_000, Transaction::STATUS_COMPLETED, test()->otherCustomer->id, test()->otherBank->id);
    sellFor($this->invoice->number, 7_000_000);
    sellFor($this->invoice->number, 3_000_000);

    $invoice = app(StandaloneInvoiceSettlement::class)->reconcile($this->invoice, $this->user);

    expect($invoice->isMarkedPaid())->toBeTrue()
        ->and($invoice->paid_by)->toBe($this->user->id);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($invoice);
    expect($snapshot['status'])->toBe(StandaloneInvoice::STATUS_PAID)
        ->and($snapshot['is_paid'])->toBeTrue();
});

it('does not treat cash-in alone as paid without matching sell', function () {
    cashInFor($this->invoice->number, 10_000_000);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice);

    expect($snapshot['is_paid'])->toBeFalse()
        ->and($snapshot['status'])->toBe(StandaloneInvoice::STATUS_PARTIAL);
});

it('auto-pays when discount is edited after sell and cash-in already exist', function () {
    cashInFor($this->invoice->number, 9_400_000);
    sellFor($this->invoice->number, 9_400_000);

    $this->actingAs($this->user)
        ->patch(route('invoice-maker.discount', $this->invoice), [
            'discount_amount' => 600_000,
        ])
        ->assertRedirect(route('invoice-maker.show', $this->invoice))
        ->assertSessionHas('success', 'Discount saved. Invoice marked as paid — sell, cash-in, and invoice amounts match.');

    $invoice = $this->invoice->fresh();
    expect((float) $invoice->discount_amount)->toBe(600_000.0)
        ->and($invoice->billedAmount())->toBe(9_400_000.0)
        ->and($invoice->isMarkedPaid())->toBeTrue();
});

it('auto-pays when the invoice maker form is saved after sell and cash-in', function () {
    cashInFor($this->invoice->number, 9_400_000);
    sellFor($this->invoice->number, 9_400_000);

    $this->actingAs($this->user)->put(route('invoice-maker.update', $this->invoice), [
        'number' => $this->invoice->number,
        'date' => $this->invoice->date->format('Y-m-d'),
        'recipient' => $this->invoice->recipient,
        'preset_id' => 'default',
        'discount_amount' => 600_000,
        'lines' => [
            ['description' => 'Jersey', 'quantity' => 1, 'price' => 10_000_000],
        ],
    ])->assertRedirect(route('invoice-maker.show', $this->invoice));

    expect($this->invoice->fresh()->isMarkedPaid())->toBeTrue()
        ->and((float) $this->invoice->fresh()->discount_amount)->toBe(600_000.0);
});

it('clears paid status when a later invoice edit breaks the match', function () {
    cashInFor($this->invoice->number, 10_000_000);
    sellFor($this->invoice->number, 10_000_000);
    app(StandaloneInvoiceSettlement::class)->reconcile($this->invoice, $this->user);
    expect($this->invoice->fresh()->isMarkedPaid())->toBeTrue();

    $this->actingAs($this->user)->put(route('invoice-maker.update', $this->invoice), [
        'number' => $this->invoice->number,
        'date' => $this->invoice->date->format('Y-m-d'),
        'recipient' => $this->invoice->recipient,
        'preset_id' => 'default',
        'discount_amount' => 0,
        'lines' => [
            ['description' => 'Jersey', 'quantity' => 1, 'price' => 11_000_000],
        ],
    ])->assertRedirect();

    expect($this->invoice->fresh()->isMarkedPaid())->toBeFalse()
        ->and((float) $this->invoice->fresh()->subtotal)->toBe(11_000_000.0);
});

it('rejects a discount greater than the subtotal', function () {
    $this->actingAs($this->user)
        ->patch(route('invoice-maker.discount', $this->invoice), [
            'discount_amount' => 11_000_000,
        ])
        ->assertSessionHasErrors('discount_amount');
});

it('ignores bank transfers when deciding paid status', function () {
    cashInFor($this->invoice->number, 10_000_000);
    sellFor($this->invoice->number, 10_000_000);
    Transaction::factory()->create([
        'type' => Transaction::TYPE_TRANSFER,
        'invoice' => $this->invoice->number,
        'total' => -1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $snapshot = app(StandaloneInvoiceSettlement::class)->snapshot($this->invoice);

    expect($snapshot['is_paid'])->toBeTrue()
        ->and($snapshot['paid_total'])->toBe(10_000_000.0)
        ->and($snapshot['sell_total'])->toBe(10_000_000.0);
});

it('forbids discount updates without invoice-maker-edit', function () {
    $staff = User::factory()->create();
    Permission::firstOrCreate(['name' => 'invoice-maker-list']);
    Permission::firstOrCreate(['name' => 'invoice-maker-edit']);
    $staff->givePermissionTo('invoice-maker-list');

    $this->actingAs($staff)
        ->patch(route('invoice-maker.discount', $this->invoice), [
            'discount_amount' => 100_000,
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
        ->assertSee('Linked sell', false)
        ->assertDontSee('data-testid="invoice-mark-paid"', false);
});
