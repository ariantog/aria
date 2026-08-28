<?php

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('signed balance rules', function () {
    it('marks buy and return totals positive and sell or return-supplier negative', function () {
        expect(Transaction::signedAmount(Transaction::TYPE_BUY, 1000))->toBe(1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_RETURN, 1000))->toBe(1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_CASH_IN, 1000))->toBe(1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_SELL, 1000))->toBe(-1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_RETURN_SUPPLIER, 1000))->toBe(-1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_CASH_OUT, 1000))->toBe(-1000.0)
            ->and(Transaction::signedAmount(Transaction::TYPE_TRANSFER, 1000))->toBe(-1000.0);
    });

    it('posts buy as positive sender balance and sell as negative receiver balance', function () {
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $item = Item::factory()->create(['qty' => 0]);

        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 50,
        ]);

        $this->post(route('transactions.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'items' => [['item_id' => $item->id, 'quantity' => 10, 'price' => 5000]],
        ])->assertRedirect();

        $buy = Transaction::latest('id')->first();
        expect((float) $buy->total)->toBe(50000.0)
            ->and((float) $buy->sender_balance)->toBe(50000.0);

        $this->post(route('transactions.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'items' => [['item_id' => $item->id, 'quantity' => 2, 'price' => 10000]],
        ])->assertRedirect();

        $sell = Transaction::latest('id')->first();
        expect((float) $sell->total)->toBe(-20000.0)
            ->and((float) $sell->receiver_balance)->toBe(-20000.0);
    });
});

describe('august calendar running balances', function () {
    function seedAugustBuy(Addrbook $supplier, Addrbook $warehouse, Item $item, string $date, int $qty, int $price): Transaction
    {
        test()->post(route('transactions.store'), [
            'date' => $date,
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'items' => [['item_id' => $item->id, 'quantity' => $qty, 'price' => $price]],
        ])->assertRedirect();

        return Transaction::latest('id')->first();
    }

    it('inserting on Aug 1 shifts running balances on later August rows', function () {
        Carbon::setTestNow('2026-08-27');

        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug10 = seedAugustBuy($supplier, $warehouse, $item, '2026-08-10', 10, 5000);
        $aug20 = seedAugustBuy($supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $aug10->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0);

        seedAugustBuy($supplier, $warehouse, $item, '2026-08-01', 20, 5000);
        $aug1 = Transaction::orderBy('date')->orderBy('id')->first();

        expect((float) $aug1->sender_balance)->toBe(100000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(150000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(160000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(160000.0);
    });

    it('deleting an older August transaction recalculates later running balances', function () {
        Carbon::setTestNow('2026-08-27');

        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug1 = seedAugustBuy($supplier, $warehouse, $item, '2026-08-01', 10, 5000);
        $aug10 = seedAugustBuy($supplier, $warehouse, $item, '2026-08-10', 5, 2000);
        $aug20 = seedAugustBuy($supplier, $warehouse, $item, '2026-08-20', 2, 5000);

        expect((float) $aug1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(70000.0);

        $this->delete(route('transactions.destroy', $aug1))
            ->assertRedirect(route('transactions.index'));

        expect(Transaction::find($aug1->id))->toBeNull()
            ->and((float) $aug10->fresh()->sender_balance)->toBe(10000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(20000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(20000.0);
    });
});

describe('back-dated insert recalculates later running balances', function () {
    function seedBuyTransaction(Addrbook $supplier, Addrbook $warehouse, Item $item, string $date, int $qty, int $price): Transaction
    {
        test()->post(route('transactions.store'), [
            'date' => $date,
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'items' => [['item_id' => $item->id, 'quantity' => $qty, 'price' => $price]],
        ])->assertRedirect();

        return Transaction::latest('id')->first();
    }

    it('recalculates supplier balance after a back-dated buy', function () {
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $day1 = now()->subDays(5)->toDateString();
        $day5 = now()->subDay()->toDateString();

        $first = seedBuyTransaction($supplier, $warehouse, $item, $day1, 10, 5000);
        $later = seedBuyTransaction($supplier, $warehouse, $item, $day5, 5, 2000);

        expect((float) $first->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(60000.0);

        $day3 = now()->subDays(3)->toDateString();
        seedBuyTransaction($supplier, $warehouse, $item, $day3, 20, 5000);
        $middle = Transaction::latest('id')->first();

        expect((float) $first->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $middle->sender_balance)->toBe(150000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(160000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(160000.0);
    });

    it('recalculates customer balance after a back-dated sell', function () {
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 100,
        ]);

        $day1 = now()->subDays(4)->toDateString();
        $day4 = now()->toDateString();

        $this->post(route('transactions.store'), [
            'date' => $day1,
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'items' => [['item_id' => $item->id, 'quantity' => 5, 'price' => 10000]],
        ])->assertRedirect();
        $first = Transaction::latest('id')->first();

        $this->post(route('transactions.store'), [
            'date' => $day4,
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'items' => [['item_id' => $item->id, 'quantity' => 1, 'price' => 5000]],
        ])->assertRedirect();
        $later = Transaction::latest('id')->first();

        expect((float) $first->fresh()->receiver_balance)->toBe(-50000.0)
            ->and((float) $later->fresh()->receiver_balance)->toBe(-55000.0);

        $day2 = now()->subDays(2)->toDateString();
        $this->post(route('transactions.store'), [
            'date' => $day2,
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'items' => [['item_id' => $item->id, 'quantity' => 2, 'price' => 10000]],
        ])->assertRedirect();
        $middle = Transaction::latest('id')->first();

        expect((float) $first->fresh()->receiver_balance)->toBe(-50000.0)
            ->and((float) $middle->receiver_balance)->toBe(-70000.0)
            ->and((float) $later->fresh()->receiver_balance)->toBe(-75000.0);
    });

    it('recalculates both parties after a back-dated cash in', function () {
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

        $day1 = now()->subDays(3)->toDateString();
        $day3 = now()->toDateString();

        $this->post(route('transactions.cash-in.store'), [
            'date' => $day1,
            'account_id' => $bank->id,
            'items' => [['customer_id' => $customer->id, 'total' => 1000]],
        ])->assertRedirect();
        $first = Transaction::latest('id')->first();

        $this->post(route('transactions.cash-in.store'), [
            'date' => $day3,
            'account_id' => $bank->id,
            'items' => [['customer_id' => $customer->id, 'total' => 500]],
        ])->assertRedirect();
        $later = Transaction::latest('id')->first();

        expect((float) $first->fresh()->sender_balance)->toBe(1000.0)
            ->and((float) $first->fresh()->receiver_balance)->toBe(1000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(1500.0);

        $day2 = now()->subDays(1)->toDateString();
        $this->post(route('transactions.cash-in.store'), [
            'date' => $day2,
            'account_id' => $bank->id,
            'items' => [['customer_id' => $customer->id, 'total' => 2000]],
        ])->assertRedirect();
        $middle = Transaction::latest('id')->first();

        expect((float) $first->fresh()->sender_balance)->toBe(1000.0)
            ->and((float) $middle->sender_balance)->toBe(3000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(3500.0)
            ->and((float) AddrbookStat::where('customer_id', $customer->id)->value('balance'))->toBe(3500.0)
            ->and((float) AddrbookStat::where('customer_id', $bank->id)->value('balance'))->toBe(3500.0);
    });
});

describe('edit and delete integrity', function () {
    function seedPostedBuy(
        TransactionService $service,
        Addrbook $supplier,
        Addrbook $warehouse,
        Item $item,
        string $date,
        int $qty,
        int $price,
    ): Transaction {
        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_BUY,
            'date' => $date,
            'sender_id' => $supplier->id,
            'sender_type' => $supplier->type,
            'receiver_id' => $warehouse->id,
            'receiver_type' => $warehouse->type,
            'total' => $qty * $price,
            'real_total' => $qty * $price,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => $qty,
            'price' => $price,
            'total' => $qty * $price,
        ]);
        $service->handleTransaction($transaction);

        return $transaction;
    }

    it('reposts edited totals and recalculates later running balances', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $day1 = now()->subDays(4)->toDateString();
        $day3 = now()->subDays(1)->toDateString();

        $tx1 = Transaction::factory()->create([
            'type' => Transaction::TYPE_BUY,
            'date' => $day1,
            'sender_id' => $supplier->id,
            'sender_type' => $supplier->type,
            'receiver_id' => $warehouse->id,
            'receiver_type' => $warehouse->type,
            'total' => 50000,
            'real_total' => 50000,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $tx1->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 5000,
            'total' => 50000,
        ]);
        $service->handleTransaction($tx1);

        $tx2 = Transaction::factory()->create([
            'type' => Transaction::TYPE_BUY,
            'date' => $day3,
            'sender_id' => $supplier->id,
            'sender_type' => $supplier->type,
            'receiver_id' => $warehouse->id,
            'receiver_type' => $warehouse->type,
            'total' => 25000,
            'real_total' => 25000,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $tx2->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'price' => 5000,
            'total' => 25000,
        ]);
        $service->handleTransaction($tx2);

        expect((float) $tx1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $tx2->fresh()->sender_balance)->toBe(75000.0);

        $service->editTransaction($tx1, function (Transaction $transaction) {
            $transaction->update(['total' => 100000, 'real_total' => 100000]);
            $transaction->details()->update(['quantity' => 20, 'total' => 100000]);
        });

        expect((float) $tx1->fresh()->sender_balance)->toBe(100000.0)
            ->and((float) $tx2->fresh()->sender_balance)->toBe(125000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(125000.0)
            ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))->toBe(25.0);
    });

    it('moving an August transaction earlier reorders later running balances', function () {
        Carbon::setTestNow('2026-08-27');

        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug10 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-10', 10, 5000);
        $aug20 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $aug10->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0);

        $service->editTransaction($aug20, function (Transaction $transaction) {
            $transaction->update(['date' => '2026-08-05']);
        });

        expect((float) $aug20->fresh()->sender_balance)->toBe(10000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(60000.0);
    });

    it('moving an August transaction later shifts intermediate running balances', function () {
        Carbon::setTestNow('2026-08-27');

        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug1 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-01', 10, 5000);
        $aug10 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-10', 2, 5000);
        $aug20 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-20', 1, 5000);

        expect((float) $aug1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(65000.0);

        $service->editTransaction($aug10, function (Transaction $transaction) {
            $transaction->update(['date' => '2026-08-25']);
        });

        expect((float) $aug1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(55000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(65000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(65000.0);
    });

    it('editing date and amount on an August row matches back-dated insert totals', function () {
        Carbon::setTestNow('2026-08-27');

        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug10 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-10', 10, 5000);
        $aug20 = seedPostedBuy($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $aug10->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0);

        $service->editTransaction($aug10, function (Transaction $transaction) {
            $transaction->update([
                'date' => '2026-08-01',
                'total' => 100000,
                'real_total' => 100000,
            ]);
            $transaction->details()->update([
                'quantity' => 20,
                'total' => 100000,
            ]);
        });

        expect((float) $aug10->fresh()->sender_balance)->toBe(100000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(110000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(110000.0);
    });

    it('changing the supplier party recalculates both old and new running balances', function () {
        Carbon::setTestNow('2026-08-27');

        $service = app(TransactionService::class);
        $supplierA = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $supplierB = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug1 = seedPostedBuy($service, $supplierA, $warehouse, $item, '2026-08-01', 10, 5000);
        $aug10 = seedPostedBuy($service, $supplierA, $warehouse, $item, '2026-08-10', 2, 5000);

        expect((float) $aug1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplierB->id)->value('balance'))->toBe(0.0);

        $service->editTransaction($aug10, function (Transaction $transaction) use ($supplierB) {
            $transaction->update([
                'sender_id' => $supplierB->id,
                'sender_type' => $supplierB->type,
            ]);
        });

        expect((float) $aug1->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(10000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplierA->id)->value('balance'))->toBe(50000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplierB->id)->value('balance'))->toBe(10000.0);
    });

    it('rejects deleting a transaction inside a closed book period', function () {
        Carbon::setTestNow('2026-03-15');

        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);
        $service = app(TransactionService::class);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_BUY,
            'date' => '2026-01-15',
            'sender_id' => $supplier->id,
            'sender_type' => $supplier->type,
            'receiver_id' => $warehouse->id,
            'receiver_type' => $warehouse->type,
            'total' => 1000,
            'real_total' => 1000,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);
        $service->handleTransaction($transaction);

        $this->delete(route('transactions.destroy', $transaction))
            ->assertSessionHasErrors('date');

        expect(Transaction::find($transaction->id))->not->toBeNull();
    });
});

it('updates addrbook stat balance after the summary queue job runs', function () {
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [['customer_id' => $customer->id, 'total' => 4200]],
    ])->assertRedirect();

    $transaction = Transaction::latest('id')->first();

    UpdateTransactionSummaries::dispatchSync($transaction->id);

    expect((float) AddrbookStat::where('customer_id', $customer->id)->value('balance'))->toBe(4200.0)
        ->and((float) AddrbookStat::where('customer_id', $bank->id)->value('balance'))->toBe(4200.0);
});

describe('jubelio sync ui', function () {
    it('hides sync controls when jubelio integration is inactive', function () {
        config(['services.jubelio.active' => false]);

        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $item = Item::factory()->create(['jubelio_item_id' => 123]);

        Jubeliosync::create([
            'jubelio_store_id' => 10,
            'jubelio_store_name' => 'Store',
            'jubelio_location_id' => 1,
            'jubelio_location_name' => 'Main',
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'bin_id' => 0,
        ]);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_SELL,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $warehouse->id,
            'sender_type' => $warehouse->type,
            'receiver_id' => $customer->id,
            'receiver_type' => $customer->type,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);

        $presented = app(JubelioTransactionSyncPresenter::class)->present($transaction);

        expect($presented['can_sync'])->toBeTrue()
            ->and($presented['sync_cek'])->toBe('S');

        $showUi = config('services.jubelio.active')
            && $presented['can_sync']
            && $presented['sync_cek'];

        expect($showUi)->toBeFalse();

        $response = $this->get(route('transactions.show', $transaction));
        $response->assertSuccessful();
        $response->assertDontSee('Sinkron Jubelio', false);
    });

    it('shows sync controls when jubelio integration is active and mapping exists', function () {
        config(['services.jubelio.active' => true]);

        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $item = Item::factory()->create(['jubelio_item_id' => 456]);

        Jubeliosync::create([
            'jubelio_store_id' => 11,
            'jubelio_store_name' => 'Store B',
            'jubelio_location_id' => 2,
            'jubelio_location_name' => 'Branch',
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'bin_id' => 0,
        ]);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_SELL,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $warehouse->id,
            'sender_type' => $warehouse->type,
            'receiver_id' => $customer->id,
            'receiver_type' => $customer->type,
        ]);
        \App\Models\TransactionDetail::factory()->create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);

        $response = $this->get(route('transactions.show', $transaction));
        $response->assertSuccessful();
        $response->assertSee('Sinkron Jubelio', false);
        $response->assertSee('Push to Jubelio', false);
    });
});
