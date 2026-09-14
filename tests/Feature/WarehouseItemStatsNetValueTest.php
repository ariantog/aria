<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\WarehouseItemStatsRecorder;
use App\Services\WarehouseItemStatsRebuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records sold value with header discount and per-line adjustment share', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $itemA = Item::factory()->create();
    $itemB = Item::factory()->create();
    $date = now()->startOfMonth()->toDateString();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => $date,
        'discount' => 10,
        'adjustment' => -1_000,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) $customer->type,
    ]);

    $detailA = $transaction->details()->create([
        'item_id' => $itemA->id,
        'quantity' => 1,
        'price' => 40_000,
        'total' => 40_000,
        'date' => $date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $detailB = $transaction->details()->create([
        'item_id' => $itemB->id,
        'quantity' => 1,
        'price' => 60_000,
        'total' => 60_000,
        'date' => $date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $recorder = app(WarehouseItemStatsRecorder::class);
    $recorder->recordDetail($transaction, $detailA);
    $recorder->recordDetail($transaction, $detailB);

    $statA = WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $itemA->id)
        ->first();
    $statB = WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $itemB->id)
        ->first();

    // 40k after 10% discount = 36k; minus 500 adjustment share each line
    expect((float) $statA->sold_value)->toBe(35_500.0);
    expect((float) $statB->sold_value)->toBe(53_500.0);
});

it('rebuilds monthly sold value with adjustment share in sql aggregate', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();
    $period = CarbonImmutable::now()->startOfMonth();
    $date = $period->toDateString();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => $date,
        'discount' => 0,
        'adjustment' => -2_000,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) $customer->type,
    ]);

    $transaction->details()->create([
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 10_000,
        'total' => 20_000,
        'date' => $date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    app(WarehouseItemStatsRebuilder::class)->rebuildMonth($period);

    $stat = WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $stat->sold_value)->toBe(18_000.0);
});
