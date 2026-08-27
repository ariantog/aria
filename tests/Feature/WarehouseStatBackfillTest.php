<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItemMonthlyStat;
use App\Models\WarehouseStatBackfill;
use App\Services\WarehouseStatBackfillService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Addrbook::factory()->warehouse()->create();
    $this->customer = Addrbook::factory()->customer()->create();
    $group = ItemGroup::factory()->create(['master' => 'CX90050', 'variant' => '02']);
    $this->item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM]);
});

function backfillSell(\Carbon\CarbonInterface $date, float $qty): void
{
    $test = test();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $test->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $test->customer->id,
        'date' => $date->toDateString(),
        'user_id' => $test->user->id,
    ])->details()->create([
        'item_id' => $test->item->id,
        'quantity' => $qty,
        'price' => 1000,
        'total' => $qty * 1000,
        'date' => $date->toDateString(),
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $test->warehouse->id,
        'receiver_id' => $test->customer->id,
    ]);
}

it('walks history newest first across batches until complete', function () {
    // Five consecutive months of activity, oldest four months back.
    for ($i = 0; $i <= 4; $i++) {
        backfillSell(now()->startOfMonth()->subMonths($i), 2);
    }

    $backfill = app(WarehouseStatBackfillService::class);
    $state = $backfill->start();

    expect($state->status)->toBe(WarehouseStatBackfill::STATUS_RUNNING);
    expect($state->months_total)->toBe(5);

    $first = $backfill->runBatch(2);

    expect($first['months'])->toBe(2);
    // Newest first: the batch starts at the current month and works backwards.
    expect($first['from'])->toBe(now()->startOfMonth()->format('Y-m'));
    expect($first['to'])->toBe(now()->startOfMonth()->subMonth()->format('Y-m'));
    expect(WarehouseItemMonthlyStat::query()->count())->toBe(2);

    $backfill->runBatch(2);
    $last = $backfill->runBatch(2);

    expect($last['status'])->toBe(WarehouseStatBackfill::STATUS_COMPLETED);
    expect(WarehouseItemMonthlyStat::query()->count())->toBe(5);
    expect($backfill->state()->months_done)->toBe(5);
});

it('does not process batches while paused and resumes where it left off', function () {
    for ($i = 0; $i <= 3; $i++) {
        backfillSell(now()->startOfMonth()->subMonths($i), 1);
    }

    $backfill = app(WarehouseStatBackfillService::class);
    $backfill->start();
    $backfill->runBatch(1);

    $cursorAfterFirst = $backfill->state()->cursor_period;

    $backfill->pause();
    $paused = $backfill->runBatch(2);

    expect($paused['months'])->toBe(0);
    expect($backfill->state()->cursor_period)->toBe($cursorAfterFirst);
    expect(WarehouseItemMonthlyStat::query()->count())->toBe(1);

    $backfill->resume();
    $backfill->runBatch(1);

    expect($backfill->state()->cursor_period)->toBe($cursorAfterFirst - 1);
    expect(WarehouseItemMonthlyStat::query()->count())->toBe(2);
});

it('reports nothing to backfill when there is no sell or return history', function () {
    $backfill = app(WarehouseStatBackfillService::class);
    $state = $backfill->start();

    expect($state->status)->toBe(WarehouseStatBackfill::STATUS_COMPLETED);
    expect($state->months_total)->toBe(0);
});

it('runs a batch from the backfill page and shows progress', function () {
    backfillSell(now()->startOfMonth(), 3);
    backfillSell(now()->startOfMonth()->subMonth(), 4);

    $this->actingAs($this->user)
        ->post(route('warehouse-stat-backfill.start'))
        ->assertRedirect();

    $this->actingAs($this->user)
        ->post(route('warehouse-stat-backfill.run-batch'), ['months' => 1])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(WarehouseItemMonthlyStat::query()->count())->toBe(1);

    $this->actingAs($this->user)
        ->get(route('warehouse-stat-backfill.index'))
        ->assertSuccessful()
        ->assertSee('Warehouse Stats Backfill')
        ->assertSee(WarehouseStatBackfill::STATUS_RUNNING);
});

it('advances the backfill through the console command', function () {
    backfillSell(now()->startOfMonth(), 5);
    backfillSell(now()->startOfMonth()->subMonth(), 6);

    $this->artisan('app:backfill-warehouse-item-stats', ['--restart' => true, '--months' => 1])
        ->assertSuccessful();

    expect(WarehouseItemMonthlyStat::query()->count())->toBe(1);

    $this->artisan('app:backfill-warehouse-item-stats', ['--months' => 5])->assertSuccessful();

    expect(WarehouseItemMonthlyStat::query()->count())->toBe(2);
    expect(app(WarehouseStatBackfillService::class)->state()->status)
        ->toBe(WarehouseStatBackfill::STATUS_COMPLETED);
});

it('records live stats for items whose type is no longer a valid enum value', function () {
    \Illuminate\Support\Facades\DB::table('items')->where('id', $this->item->id)->update(['type' => 4]);

    $date = now()->startOfMonth();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'date' => $date->toDateString(),
        'user_id' => $this->user->id,
    ]);

    $detail = $transaction->details()->create([
        'item_id' => $this->item->id,
        'quantity' => 9,
        'price' => 1000,
        'total' => 9000,
        'date' => $date->toDateString(),
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $this->warehouse->id,
        'receiver_id' => $this->customer->id,
    ]);

    app(\App\Services\WarehouseItemStatsRecorder::class)->recordDetail($transaction, $detail);

    $stat = WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $this->warehouse->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($stat)->not->toBeNull();
    expect((float) $stat->sold_qty)->toBe(9.0);
    expect($stat->item_type)->toBe(ItemType::ITEM->value);
});

it('stays idle until the backfill is started', function () {
    backfillSell(now()->startOfMonth(), 2);

    $this->artisan('app:backfill-warehouse-item-stats', ['--months' => 3])->assertSuccessful();

    expect(WarehouseItemMonthlyStat::query()->count())->toBe(0);
});
