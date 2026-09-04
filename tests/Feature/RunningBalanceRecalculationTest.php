<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function postBuyForRunningBalance(
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
        'status' => Transaction::STATUS_COMPLETED,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => $qty,
        'price' => $price,
        'total' => $qty * $price,
    ]);
    $service->handleTransaction($transaction);

    return $transaction->fresh();
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-27');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('re-derive later running balances', function () {
    it('rebuilds a stale later row instead of incrementing the corrupt value', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $first = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-01', 10, 5000);
        $later = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $first->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(60000.0);

        DB::table('transactions')->where('id', $later->id)->update(['sender_balance' => 999999]);

        $middle = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-10', 20, 5000);

        expect((float) $first->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $middle->sender_balance)->toBe(150000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(160000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(160000.0);
    });

    it('recalculates later rows when a completed transaction date is edited directly', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug10 = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-10', 10, 5000);
        $aug20 = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $aug10->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0);

        $aug20->update(['date' => '2026-08-05']);

        expect((float) $aug20->fresh()->sender_balance)->toBe(10000.0)
            ->and((float) $aug10->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(60000.0);
    });

    it('recalculates later rows when a completed transaction total is edited directly', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $first = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-01', 10, 5000);
        $later = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        $first->update(['total' => 100000]);

        expect((float) $first->fresh()->sender_balance)->toBe(100000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(110000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(110000.0);
    });

    it('recalculates later rows when an older row is deleted without revertTransaction', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $older = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-01', 10, 5000);
        $later = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);

        expect((float) $later->fresh()->sender_balance)->toBe(60000.0);

        $older->delete();

        expect(Transaction::find($older->id))->toBeNull()
            ->and((float) $later->fresh()->sender_balance)->toBe(10000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(10000.0);
    });
});

describe('app:recalculate-running-balances', function () {
    it('rebuilds running balances in date order when ids are out of chronological order', function () {
        $service = app(TransactionService::class);
        $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $aug20 = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-20', 5, 2000);
        $aug01 = postBuyForRunningBalance($service, $supplier, $warehouse, $item, '2026-08-01', 10, 5000);

        expect((int) $aug20->id)->toBeLessThan((int) $aug01->id)
            ->and((float) $aug01->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0);

        DB::table('transactions')->where('id', $aug01->id)->update(['sender_balance' => 1]);
        DB::table('transactions')->where('id', $aug20->id)->update(['sender_balance' => 2]);
        AddrbookStat::where('customer_id', $supplier->id)->update(['balance' => 0]);

        $this->artisan('app:recalculate-running-balances')->assertSuccessful();

        expect((float) $aug01->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0)
            ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(60000.0);
    });

    it('normalizes a legacy positive transfer total when rebuilding', function () {
        $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
        $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

        $older = Transaction::factory()->create([
            'type' => Transaction::TYPE_TRANSFER,
            'date' => '2026-08-01',
            'sender_id' => $bankSource->id,
            'sender_type' => $bankSource->type,
            'receiver_id' => $bankDest->id,
            'receiver_type' => $bankDest->type,
            'total' => 1000,
            'sender_balance' => 0,
            'receiver_balance' => 0,
        ]);
        $later = Transaction::factory()->create([
            'type' => Transaction::TYPE_TRANSFER,
            'date' => '2026-08-20',
            'sender_id' => $bankSource->id,
            'sender_type' => $bankSource->type,
            'receiver_id' => $bankDest->id,
            'receiver_type' => $bankDest->type,
            'total' => -500,
            'sender_balance' => 0,
            'receiver_balance' => 0,
        ]);

        $this->artisan('app:recalculate-running-balances')->assertSuccessful();

        expect((float) $older->fresh()->sender_balance)->toBe(-1000.0)
            ->and((float) $older->fresh()->receiver_balance)->toBe(1000.0)
            ->and((float) $later->fresh()->sender_balance)->toBe(-1500.0)
            ->and((float) $later->fresh()->receiver_balance)->toBe(1500.0)
            ->and((float) AddrbookStat::where('customer_id', $bankSource->id)->value('balance'))->toBe(-1500.0)
            ->and((float) AddrbookStat::where('customer_id', $bankDest->id)->value('balance'))->toBe(1500.0);
    });

    it('limits rebuild to one addrbook when requested', function () {
        $service = app(TransactionService::class);
        $supplierA = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $supplierB = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
        $item = Item::factory()->create(['qty' => 0]);

        $buyA = postBuyForRunningBalance($service, $supplierA, $warehouse, $item, '2026-08-01', 10, 5000);
        $buyB = postBuyForRunningBalance($service, $supplierB, $warehouse, $item, '2026-08-01', 2, 5000);

        DB::table('transactions')->where('id', $buyA->id)->update(['sender_balance' => 1]);
        DB::table('transactions')->where('id', $buyB->id)->update(['sender_balance' => 2]);

        $this->artisan('app:recalculate-running-balances', ['--addrbook' => $supplierA->id])->assertSuccessful();

        expect((float) $buyA->fresh()->sender_balance)->toBe(50000.0)
            ->and((float) $buyB->fresh()->sender_balance)->toBe(2.0);
    });
});
