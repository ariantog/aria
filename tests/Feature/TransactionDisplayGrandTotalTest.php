<?php

use App\Models\Addrbook;
use App\Models\DeletedTransaction;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(); // id 1 — superadmin
});

it('uses seller income for legacy jubelio sell rows that double-count adjustment', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
    ]);

    expect($transaction->displayGrandTotal())->toBe(82350.0);
});

it('reads the payable from header total only', function () {
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -42935,
        'adjustment' => -21065,
    ]);

    expect($sell->displayGrandTotal())->toBe(42935.0);
});

it('shows signed header total on transaction list and detail', function () {
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'total' => -150_000,
        'real_total' => -150_000,
        'invoice' => 'INV-SIGNED-SELL',
    ]);
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'total' => 75_000,
        'real_total' => 75_000,
        'invoice' => 'INV-SIGNED-BUY',
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('-150,000', false)
        ->assertSee('75,000', false);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('-150,000', false);
});

it('shows signed header total on legacy jubelio transaction detail', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-LEGACY-GRAND',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 169000,
        'discount' => 0,
        'total' => 169000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('-82,350', false);
});

it('uses line-item sum for summary subtotal when header total is net receivable', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 169000,
        'discount' => 0,
        'total' => 169000,
    ]);

    $transaction->load('details');

    expect($transaction->displaySummarySubtotal())->toBe(-169000.0)
        ->and($transaction->displayGrandTotal())->toBe(82350.0);
});

it('treats a 100 percent invoice discount as a full write-off of the line subtotal', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'real_total' => -1_591_000,
        'total' => 0,
        'discount' => 100,
        'adjustment' => 0,
        'ppn' => 0,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 1_591_000,
        'discount' => 0,
        'total' => 1_591_000,
    ]);

    $transaction->load('details');

    expect($transaction->invoiceDiscountPercent())->toBe(100.0)
        ->and($transaction->displayInvoiceDiscountAmount())->toBe(1_591_000.0)
        ->and($transaction->displaySignedInvoiceDiscount())->toBe(1_591_000.0)
        ->and($transaction->displaySummarySubtotal())->toBe(-1_591_000.0)
        ->and($transaction->displayReconstructedSignedTotal())->toBe(0.0)
        ->and($transaction->displayGrandTotal())->toBe(0.0)
        ->and($transaction->displaySignedGrandTotal())->toBe(0.0);
});

it('shows 100 percent invoice discount as the full amount and a zero payable', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'invoice' => 'INV-FULL-DISC',
        'real_total' => -1_591_000,
        'total' => 0,
        'discount' => 100,
        'adjustment' => 0,
        'ppn' => 0,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 1_591_000,
        'discount' => 0,
        'total' => 1_591_000,
    ]);

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('data-testid="legacy-total-mismatch"')
        ->and(preg_match('/data-testid="tx-invoice-discount-amount"[^>]*>(-?[^<]+)/', $html, $discountMatch))->toBe(1)
        ->and(trim($discountMatch[1]))->toBe('+1,591,000')
        ->and(preg_match('/data-testid="tx-grand-total"[^>]*>IDR ([^<]+)/', $html, $totalMatch))->toBe(1)
        ->and(trim($totalMatch[1]))->toBe('0');

    $list = $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('INV-FULL-DISC', false)
        ->getContent();

    expect(preg_match('/data-testid="tx-list-total-'.$transaction->id.'"[^>]*>([^<]+)/', $list, $listMatch))->toBe(1)
        ->and(trim($listMatch[1]))->toBe('0');
});

it('exposes display helpers on deleted transactions and renders deleted show', function () {
    $deleted = DeletedTransaction::create([
        'id' => 615223,
        'date' => now()->toDateString(),
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DELETED-GRAND',
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'discount' => 0,
        'ppn' => 0,
        'total_items' => 1,
        'status' => Transaction::STATUS_COMPLETED,
        'deleted_at' => now(),
    ]);

    expect($deleted->displayGrandTotal())->toBe(82350.0);

    $this->actingAs($this->user)
        ->get(route('transactions.deleted.show', $deleted->id))
        ->assertOk()
        ->assertSee('-82,350', false);
});

it('reconstructs sell payable from signed discount and adjustment contributions', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'total' => -17_000,
        'discount' => 10,
        'adjustment' => -1_000,
        'ppn' => 0,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
    ]);
    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 10_000,
        'discount' => 0,
        'total' => 20_000,
    ]);
    $transaction->load('details');

    expect($transaction->displaySummarySubtotal())->toBe(-20_000.0)
        ->and($transaction->displaySignedInvoiceDiscount())->toBe(2_000.0)
        ->and($transaction->displaySignedAdjustment())->toBe(1_000.0)
        ->and($transaction->displayReconstructedSignedTotal())->toBe(-17_000.0)
        ->and($transaction->hasLegacyTotalMismatch())->toBeFalse()
        ->and($transaction->displayReconstructedSignedTotal())->toBe($transaction->displaySignedGrandTotal());

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('data-testid="legacy-total-mismatch"')
        ->and(preg_match('/data-testid="tx-invoice-discount-amount"[^>]*>(-?[^<]+)/', $html, $discountMatch))->toBe(1)
        ->and(trim($discountMatch[1]))->toBe('+2,000')
        ->and(preg_match('/data-testid="tx-adjustment-amount"[^>]*>(-?[^<]+)/', $html, $adjMatch))->toBe(1)
        ->and(trim($adjMatch[1]))->toBe('+1,000');
});

it('reconstructs jubelio sell receivable from a negative marketplace adjustment', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -42935,
        'adjustment' => -21065,
        'discount' => 0,
        'ppn' => 0,
    ]);
    $item = Item::factory()->create();
    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 1,
        'receiver_id' => 1,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 64000,
        'discount' => 0,
        'total' => 64000,
    ]);
    $transaction->load('details');

    expect($transaction->displaySignedAdjustment())->toBe(21065.0)
        ->and($transaction->displayReconstructedSignedTotal())->toBe(-42935.0)
        ->and($transaction->hasLegacyTotalMismatch())->toBeFalse()
        ->and($transaction->displayReconstructedSignedTotal())->toBe($transaction->displaySignedGrandTotal());
});

it('flags old sells whose stored total omitted the invoice discount', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'invoice' => 'INV-OLD-DISC',
        'total' => -1_591_000,
        'discount' => 100,
        'adjustment' => 0,
        'ppn' => 0,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'user_id' => $this->user->id,
    ]);
    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 1_591_000,
        'discount' => 0,
        'total' => 1_591_000,
    ]);
    $transaction->load('details');

    expect($transaction->displayReconstructedSignedTotal())->toBe(0.0)
        ->and($transaction->displaySignedGrandTotal())->toBe(-1_591_000.0)
        ->and($transaction->hasLegacyTotalMismatch())->toBeTrue()
        ->and($transaction->displayReconstructedSignedTotal())->not->toBe($transaction->displaySignedGrandTotal());

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-testid="legacy-total-mismatch"');
});

it('does not flag faktur sells that store DPP on total and PPN separately', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'total' => -100_000,
        'discount' => 0,
        'adjustment' => 0,
        'ppn' => 11_000,
    ]);
    $item = Item::factory()->create();
    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 1,
        'receiver_id' => 1,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100_000,
        'discount' => 0,
        'total' => 100_000,
    ]);
    $transaction->load('details');

    expect($transaction->displayReconstructedSignedTotal())->toBe(-111_000.0)
        ->and($transaction->displaySignedGrandTotal())->toBe(-100_000.0)
        ->and($transaction->hasLegacyTotalMismatch())->toBeFalse();
});
