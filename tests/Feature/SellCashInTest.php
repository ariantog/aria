<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\StandaloneInvoice;
use App\Models\StandaloneInvoiceLine;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\UserPreferenceService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create(); // id 1 — superadmin
    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Utama']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'PT Pelanggan']);
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'BCA Kas']);
    $this->otherBank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'Mandiri']);

    $this->sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV/SELL/2026/0100',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'total' => -1_500_000,
        'real_total' => -1_500_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);
});

it('shows the cash in switch on a sell transaction page', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false)
        ->assertSee('data-testid="sell-cash-in-amount"', false)
        ->assertSee('data-testid="sell-cash-in-bank"', false)
        ->assertSee('data-testid="sell-cash-in-date"', false)
        ->assertSee('value="'.$this->bank->id.'"', false);
});

it('preselects the user cash in default bank on the sell page', function () {
    app(UserPreferenceService::class)->set($this->user, 'transactions.default_cash_in_bank_id', $this->otherBank->id);

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-testid="sell-cash-in-bank"')
        ->and($html)->toContain('value="'.$this->otherBank->id.'" selected');
});

it('hides the cash in switch on non-sell transaction pages', function () {
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'INV/BUY/1',
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $buy))
        ->assertOk()
        ->assertDontSee('data-testid="sell-cash-in-switch"', false);
});

it('creates a cash in from a sell with the same invoice and sell receiver as sender', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $this->sell), [
            'amount' => 1_250_000,
            'account_id' => $this->bank->id,
            'date' => '2026-08-15',
        ])
        ->assertRedirect(route('transactions.show', $this->sell))
        ->assertSessionHas('success', 'Cash In created.');

    $cashIn = Transaction::query()
        ->where('type', Transaction::TYPE_CASH_IN)
        ->where('invoice', $this->sell->invoice)
        ->first();

    expect($cashIn)->not->toBeNull()
        ->and((int) $cashIn->sender_id)->toBe($this->customer->id)
        ->and((int) $cashIn->sender_type)->toBe(Addrbook::TYPE_CUSTOMER)
        ->and((int) $cashIn->receiver_id)->toBe($this->bank->id)
        ->and((int) $cashIn->receiver_type)->toBe(Addrbook::TYPE_BANK)
        ->and((float) $cashIn->total)->toBe(1_250_000.0)
        ->and((float) $cashIn->real_total)->toBe(1_250_000.0)
        ->and($cashIn->date->toDateString())->toBe('2026-08-15')
        ->and($cashIn->invoice)->toBe($this->sell->invoice);
});

it('defaults the cash in date to today when the date is omitted', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $this->sell), [
            'amount' => 500_000,
            'account_id' => $this->bank->id,
        ])
        ->assertRedirect(route('transactions.show', $this->sell));

    $cashIn = Transaction::query()->where('type', Transaction::TYPE_CASH_IN)->latest('id')->first();

    expect($cashIn->date->toDateString())->toBe(now()->toDateString());
});

it('marks the invoice maker paid after a matching sell cash in', function () {
    $invoice = StandaloneInvoice::factory()->create([
        'number' => $this->sell->invoice,
        'subtotal' => 1_500_000,
        'discount_amount' => 0,
        'user_id' => $this->user->id,
    ]);
    StandaloneInvoiceLine::factory()->create([
        'standalone_invoice_id' => $invoice->id,
        'quantity' => 1,
        'price' => 1_500_000,
        'total' => 1_500_000,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $this->sell), [
            'amount' => 1_500_000,
            'account_id' => $this->bank->id,
        ])
        ->assertRedirect();

    expect($invoice->fresh()->isMarkedPaid())->toBeTrue();
});

it('defaults the cash in amount to the invoice remaining when invoice maker is linked', function () {
    $invoice = StandaloneInvoice::factory()->create([
        'number' => $this->sell->invoice,
        'subtotal' => 1_500_000,
        'discount_amount' => 0,
        'user_id' => $this->user->id,
    ]);
    StandaloneInvoiceLine::factory()->create([
        'standalone_invoice_id' => $invoice->id,
        'quantity' => 1,
        'price' => 1_500_000,
        'total' => 1_500_000,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $this->sell->invoice,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $this->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $this->bank->id,
        'total' => 400_000,
        'real_total' => 400_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell))
        ->assertOk()
        ->assertSee('Linked cash-in', false)
        ->getContent();

    expect($html)->toContain('amount: 1100000');
});

it('forbids creating cash in from a sell without cash-in permission', function () {
    $staff = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    Permission::firstOrCreate(['name' => 'transactions-type-cash-in']);
    $staff->givePermissionTo('transactions-show');

    $this->actingAs($staff)
        ->post(route('transactions.sell-cash-in.store', $this->sell), [
            'amount' => 100_000,
            'account_id' => $this->bank->id,
        ])
        ->assertForbidden();

    expect(Transaction::query()->where('type', Transaction::TYPE_CASH_IN)->count())->toBe(0);
});

it('hides the cash in switch and submit without transactions-type-cash-in', function () {
    $staff = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'transactions-type-cash-in']);
    $staff->givePermissionTo(['transactions-show', 'transactions-type-sell']);

    $this->actingAs($staff)
        ->get(route('transactions.show', $this->sell))
        ->assertOk()
        ->assertDontSee('data-testid="sell-cash-in-switch"', false)
        ->assertDontSee('data-testid="sell-cash-in-submit"', false);

    $this->actingAs($staff)
        ->get(route('transactions.create', 'sell'))
        ->assertOk()
        ->assertDontSee('data-testid="sell-cash-in-switch"', false)
        ->assertDontSee('data-testid="sell-cash-in-submit"', false);
});

it('shows the cash in switch for staff with transactions-type-cash-in', function () {
    $staff = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'transactions-type-cash-in']);
    $staff->givePermissionTo(['transactions-show', 'transactions-type-sell', 'transactions-type-cash-in']);

    $this->actingAs($staff)
        ->get(route('transactions.show', $this->sell))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false)
        ->assertSee('data-testid="sell-cash-in-submit"', false);

    $this->actingAs($staff)
        ->get(route('transactions.create', 'sell'))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false);
});

it('rejects cash in from a non-sell transaction', function () {
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'INV/BUY/2',
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $buy), [
            'amount' => 100_000,
            'account_id' => $this->bank->id,
        ])
        ->assertStatus(422);
});

it('rejects a non-bank account', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $this->sell), [
            'amount' => 100_000,
            'account_id' => $this->customer->id,
        ])
        ->assertSessionHasErrors('account_id');
});

it('shows the cash in switch on the sell create form', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.create', 'sell'))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false)
        ->assertSee('data-testid="sell-cash-in-amount"', false)
        ->assertSee('data-testid="sell-cash-in-bank"', false);
});

it('does not show the cash in switch on the buy create form', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.create', 'buy'))
        ->assertOk()
        ->assertDontSee('data-testid="sell-cash-in-switch"', false);
});

it('creates a matching cash in when the sell form switch is on', function () {
    $item = Item::factory()->create(['price' => 50_000, 'cost' => 20_000]);
    WarehouseItem::create([
        'warehouse_id' => $this->warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 20,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'sell',
            'sender_id' => $this->warehouse->id,
            'receiver_id' => $this->customer->id,
            'invoice' => 'INV/SELL/CASH/1',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'price' => 50_000,
                'discount' => 0,
            ]],
            'create_cash_in' => true,
            'cash_in_amount' => 80_000,
            'cash_in_account_id' => $this->otherBank->id,
            'cash_in_date' => '2026-08-20',
        ])
        ->assertRedirect();

    $sell = Transaction::query()->where('type', Transaction::TYPE_SELL)->where('invoice', 'INV/SELL/CASH/1')->first();
    $cashIn = Transaction::query()->where('type', Transaction::TYPE_CASH_IN)->where('invoice', 'INV/SELL/CASH/1')->first();

    expect($sell)->not->toBeNull()
        ->and($cashIn)->not->toBeNull()
        ->and((int) $cashIn->sender_id)->toBe($this->customer->id)
        ->and((int) $cashIn->receiver_id)->toBe($this->otherBank->id)
        ->and((float) $cashIn->total)->toBe(80_000.0)
        ->and($cashIn->date->toDateString())->toBe('2026-08-20');
});

it('shows the cash in switch when the sell receiver is soft-deleted', function () {
    $this->customer->delete();

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell->fresh()))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false)
        ->assertSee('data-testid="sell-cash-in-card"', false);
});

it('shows the cash in switch on a pending sell', function () {
    $this->sell->update(['status' => Transaction::STATUS_PENDING]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell->fresh()))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false);
});

it('hides the cash in switch on a cancelled sell', function () {
    $this->sell->update(['status' => Transaction::STATUS_CANCELLED]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->sell->fresh()))
        ->assertOk()
        ->assertDontSee('data-testid="sell-cash-in-switch"', false);
});

it('creates a cash in from a pending sell and a soft-deleted receiver', function () {
    $this->sell->update(['status' => Transaction::STATUS_PENDING]);
    $this->customer->delete();

    $this->actingAs($this->user)
        ->post(route('transactions.sell-cash-in.store', $this->sell->fresh()), [
            'amount' => 250_000,
            'account_id' => $this->bank->id,
            'date' => '2026-08-18',
        ])
        ->assertRedirect(route('transactions.show', $this->sell));

    $cashIn = Transaction::query()
        ->where('type', Transaction::TYPE_CASH_IN)
        ->where('invoice', $this->sell->invoice)
        ->first();

    expect($cashIn)->not->toBeNull()
        ->and((int) $cashIn->sender_id)->toBe($this->customer->id)
        ->and((int) $cashIn->sender_type)->toBe(Addrbook::TYPE_CUSTOMER)
        ->and((float) $cashIn->total)->toBe(250_000.0);
});

it('shows the cash in switch on invoice maker when a sell is linked', function () {
    $invoice = StandaloneInvoice::factory()->create([
        'number' => $this->sell->invoice,
        'subtotal' => 1_500_000,
        'discount_amount' => 0,
        'user_id' => $this->user->id,
    ]);
    StandaloneInvoiceLine::factory()->create([
        'standalone_invoice_id' => $invoice->id,
        'quantity' => 1,
        'price' => 1_500_000,
        'total' => 1_500_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('invoice-maker.show', $invoice))
        ->assertOk()
        ->assertSee('data-testid="sell-cash-in-switch"', false)
        ->assertSee('data-testid="sell-cash-in-card"', false);
});

it('does not create cash in when the sell form switch is off', function () {
    $item = Item::factory()->create(['price' => 50_000, 'cost' => 20_000]);
    WarehouseItem::create([
        'warehouse_id' => $this->warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 20,
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'sell',
            'sender_id' => $this->warehouse->id,
            'receiver_id' => $this->customer->id,
            'invoice' => 'INV/SELL/NOCASH/1',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 50_000,
                'discount' => 0,
            ]],
        ])
        ->assertRedirect();

    expect(Transaction::query()->where('type', Transaction::TYPE_CASH_IN)->where('invoice', 'INV/SELL/NOCASH/1')->exists())->toBeFalse();
});
