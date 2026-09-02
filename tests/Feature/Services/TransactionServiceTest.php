<?php


use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    //
});

it('calculates balances correctly for sequential transactions', function () {
    $service = new TransactionService;
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
    $item = Item::factory()->create(['qty' => 0]);

    // Transaction 1 (Day 1): Buy $100 -> Balance should be $100
    $tx1 = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'date' => Carbon::now()->subDays(3)->format('Y-m-d'),
        'sender_id' => $supplier->id,
        'sender_type' => $supplier->type,
        'real_total' => 100,
        'total' => 100,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $tx1->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'price' => 10,
        'total' => 100,
    ]);

    $service->handleTransaction($tx1);
    expect($tx1->fresh()->sender_balance)->toEqual(100);

    // Transaction 2 (Day 2): Buy $50 -> Balance should be $150
    $tx2 = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
        'sender_id' => $supplier->id,
        'sender_type' => $supplier->type,
        'real_total' => 50,
        'total' => 50,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $tx2->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'price' => 10,
        'total' => 50,
    ]);
    $service->handleTransaction($tx2);
    expect($tx2->fresh()->sender_balance)->toEqual(150);

    // Check AddrbookStat
    $stat = AddrbookStat::where('customer_id', $supplier->id)->first();
    expect($stat->balance)->toEqual(150);
});

it('recalculates balances correctly for a retroactively inserted transaction', function () {
    $service = new TransactionService;
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
    $item = Item::factory()->create(['qty' => 0]);
    $date1 = Carbon::now()->subDays(5)->format('Y-m-d');
    $date3 = Carbon::now()->subDays(1)->format('Y-m-d');

    // Setup T1 ($100 on Day 1)
    $tx1 = Transaction::factory()->create(['type' => Transaction::TYPE_BUY, 'date' => $date1, 'sender_id' => $supplier->id, 'sender_type' => $supplier->type, 'real_total' => 100, 'total' => 100]);
    TransactionDetail::factory()->create(['transaction_id' => $tx1->id, 'item_id' => $item->id, 'quantity' => 10, 'price' => 10, 'total' => 100]);
    $service->handleTransaction($tx1);

    // Setup T3 ($50 on Day 5)
    $tx3 = Transaction::factory()->create(['type' => Transaction::TYPE_BUY, 'date' => $date3, 'sender_id' => $supplier->id, 'sender_type' => $supplier->type, 'real_total' => 50, 'total' => 50]);
    TransactionDetail::factory()->create(['transaction_id' => $tx3->id, 'item_id' => $item->id, 'quantity' => 5, 'price' => 10, 'total' => 50]);
    $service->handleTransaction($tx3);

    // Assert initial state: T1=100, T3=150
    expect($tx1->fresh()->sender_balance)->toEqual(100);
    expect($tx3->fresh()->sender_balance)->toEqual(150);

    // ACTION: Insert T2 retroactively ($200 on Day 3)
    $date2 = Carbon::now()->subDays(3)->format('Y-m-d');
    $tx2 = Transaction::factory()->create(['type' => Transaction::TYPE_BUY, 'date' => $date2, 'sender_id' => $supplier->id, 'sender_type' => $supplier->type, 'real_total' => 200, 'total' => 200]);
    TransactionDetail::factory()->create(['transaction_id' => $tx2->id, 'item_id' => $item->id, 'quantity' => 20, 'price' => 10, 'total' => 200]);

    // Process the retroactive transaction
    $service->handleTransaction($tx2);

    // Assert new state:
    // T1 (Day 1) remains $100
    expect($tx1->fresh()->sender_balance)->toEqual(100);
    // T2 (Day 3) should be T1(100) + T2(200) = $300
    expect($tx2->fresh()->sender_balance)->toEqual(300);
    // T3 (Day 5) should be updated to T2(300) + T3(50) = $350
    expect($tx3->fresh()->sender_balance)->toEqual(350);

    // Check AddrbookStat is now 350
    $stat = AddrbookStat::where('customer_id', $supplier->id)->first();
    expect($stat->balance)->toEqual(350);
});

it('normalizes legacy positive transfer totals when reverting balances', function () {
    $service = new TransactionService;
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $transfer = Transaction::factory()->create([
        'type' => Transaction::TYPE_TRANSFER,
        'date' => Carbon::now()->format('Y-m-d'),
        'sender_id' => $bankSource->id,
        'sender_type' => $bankSource->type,
        'receiver_id' => $bankDest->id,
        'receiver_type' => $bankDest->type,
        'total' => 15000000,
        'real_total' => 15000000,
    ]);

    $service->handleTransaction($transfer);
    expect(AddrbookStat::where('customer_id', $bankSource->id)->value('balance'))->toEqual(-15000000);
    expect(AddrbookStat::where('customer_id', $bankDest->id)->value('balance'))->toEqual(15000000);

    $service->revertTransaction($transfer);
    expect(AddrbookStat::where('customer_id', $bankSource->id)->value('balance'))->toEqual(0);
    expect(AddrbookStat::where('customer_id', $bankDest->id)->value('balance'))->toEqual(0);
});

it('applies and reverts balance changes from signed total column', function () {
    $service = new TransactionService;
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => Carbon::now()->format('Y-m-d'),
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'total' => -500,
        'real_total' => -450,
    ]);

    $service->handleTransaction($sell);
    expect($sell->fresh()->receiver_balance)->toEqual(-500);

    $service->revertTransaction($sell);
    expect(AddrbookStat::where('customer_id', $customer->id)->value('balance'))->toEqual(0);

    $transfer = Transaction::factory()->create([
        'type' => Transaction::TYPE_TRANSFER,
        'date' => Carbon::now()->format('Y-m-d'),
        'sender_id' => $bankSource->id,
        'sender_type' => $bankSource->type,
        'receiver_id' => $bankDest->id,
        'receiver_type' => $bankDest->type,
        'total' => -5000,
        'real_total' => -5000,
    ]);

    $service->handleTransaction($transfer);
    expect(AddrbookStat::where('customer_id', $bankSource->id)->value('balance'))->toEqual(-5000);
    expect(AddrbookStat::where('customer_id', $bankDest->id)->value('balance'))->toEqual(5000);

    $service->revertTransaction($transfer);
    expect(AddrbookStat::where('customer_id', $bankSource->id)->value('balance'))->toEqual(0);
    expect(AddrbookStat::where('customer_id', $bankDest->id)->value('balance'))->toEqual(0);
});

it('handles negative balances correctly when returned', function () {
    $service = new TransactionService;
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['qty' => 100]);
    $date = Carbon::now()->format('Y-m-d');

    // Sell $500 -> Customer balance becomes -500 (Debt)
    $tx1 = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => $date,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'real_total' => -500, // Now signed negative for SELL
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $tx1->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'price' => 50,
        'total' => 500,
    ]);

    $service->handleTransaction($tx1);
    expect($tx1->fresh()->receiver_balance)->toEqual(-500);

    // Customer returns $100 worth of goods -> Debt becomes $400
    $tx2 = Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'date' => $date,
        'sender_id' => $customer->id,
        'sender_type' => $customer->type,
        'real_total' => 100, // Amount is positive but logic should make it negative balance impact
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $tx2->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 50,
        'total' => 100,
    ]);

    $service->handleTransaction($tx2);
    expect($tx2->fresh()->sender_balance)->toEqual(400); // 500 - 100

    $stat = AddrbookStat::where('customer_id', $customer->id)->first();
    expect($stat->balance)->toEqual(400);
})->skip('Minus record logic is delayed as requested by user -> priority is plus.');
