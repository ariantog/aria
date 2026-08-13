<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use App\Services\JubelioStockCheckService;
use Mockery\MockInterface;

function seedStockCheckSync(Addrbook $warehouse, int $locationId = 10, string $locationName = 'Gudang Pusat'): Jubeliosync
{
    return Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store 1',
        'jubelio_location_id' => $locationId,
        'jubelio_location_name' => $locationName,
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);
}

it('compares aria qty against jubelio on-hand only', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($warehouse);

    $item = Item::factory()->create([
        'jubelio_item_id' => 123,
        'type' => Item::TYPE_ITEM,
    ]);

    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 50,
    ]);

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'processing',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->andReturn([
                'data' => [[
                    'item_id' => 123,
                    'location_stocks' => [[
                        'location_id' => 10,
                        'on_hand' => 40,
                        'on_order' => 5,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    $discrepancy = $job->fresh()->discrepancies()->first();
    expect($discrepancy)->not->toBeNull();
    expect((float) $discrepancy->aria_qty)->toBe(50.0);
    expect((float) $discrepancy->jubelio_on_hand)->toBe(40.0);
    expect((float) $discrepancy->jubelio_on_order)->toBe(5.0);
    expect((float) $discrepancy->jubelio_qty)->toBe(40.0);
    expect($job->fresh()->status)->toBe('completed');
});

it('does not flag a match when aria equals jubelio on-hand', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($warehouse);

    $item = Item::factory()->create([
        'jubelio_item_id' => 200,
        'type' => Item::TYPE_ITEM,
    ]);

    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 40,
    ]);

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'processing',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->andReturn([
                'data' => [[
                    'item_id' => 200,
                    'location_stocks' => [[
                        'location_id' => 10,
                        'on_hand' => 40,
                        'on_order' => 5,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    expect($job->fresh()->discrepancies()->count())->toBe(0);
});

it('selects high-demand skus per warehouse and type', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $sync = seedStockCheckSync($warehouse);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $hotItem = Item::factory()->create(['jubelio_item_id' => 301, 'type' => Item::TYPE_ITEM]);
    $coldItem = Item::factory()->create(['jubelio_item_id' => 302, 'type' => Item::TYPE_ITEM]);

    foreach ([$hotItem, $coldItem] as $item) {
        WarehouseItem::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => Addrbook::class,
            'quantity' => 10,
        ]);
    }

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => now()->subDays(10),
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $hotItem->id,
        'quantity' => 100,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $coldItem->id,
        'quantity' => 1,
    ]);

    $service = app(JubelioStockCheckService::class);
    $selected = $service->selectItemsForWarehouse($sync, 1, 90);

    expect($selected->pluck('id')->all())->toBe([$hotItem->id]);
});

it('selects both items and asset lancar per warehouse', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $sync = seedStockCheckSync($warehouse);

    $regular = Item::factory()->create(['jubelio_item_id' => 401, 'type' => Item::TYPE_ITEM]);
    $asset = Item::factory()->create(['jubelio_item_id' => 402, 'type' => Item::TYPE_ASSET_LANCAR]);

    foreach ([$regular, $asset] as $item) {
        WarehouseItem::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => Addrbook::class,
            'quantity' => 5,
        ]);
    }

    $service = app(JubelioStockCheckService::class);
    $selected = $service->selectItemsForWarehouse($sync, 50, 90);

    expect($selected->pluck('id')->sort()->values()->all())
        ->toBe(collect([$regular->id, $asset->id])->sort()->values()->all());
});

it('processes synced warehouses one per cron run', function () {
    $wh1 = Addrbook::factory()->warehouse()->create();
    $wh2 = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($wh1, 10, 'Gudang A');
    seedStockCheckSync($wh2, 20, 'Gudang B');

    $item = Item::factory()->create(['jubelio_item_id' => 501, 'type' => Item::TYPE_ITEM]);
    foreach ([$wh1, $wh2] as $warehouse) {
        WarehouseItem::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => Addrbook::class,
            'quantity' => 1,
        ]);
    }

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'processing',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->andReturn([
                'data' => [[
                    'item_id' => 501,
                    'location_stocks' => [['location_id' => 10, 'on_hand' => 1, 'on_order' => 0]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    $job->refresh();
    expect($job->sync_cursor)->toBe(1);
    expect($job->status)->toBe('processing');

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->andReturn([
                'data' => [[
                    'item_id' => 501,
                    'location_stocks' => [['location_id' => 20, 'on_hand' => 1, 'on_order' => 0]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    expect($job->fresh()->sync_cursor)->toBe(2);
    expect($job->fresh()->status)->toBe('completed');
});

it('ignores warehouses not mapped in jubeliosyncs', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($warehouse, 10);

    $item = Item::factory()->create(['jubelio_item_id' => 601, 'type' => Item::TYPE_ITEM]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 10,
    ]);

    JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'processing',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->with([601])
            ->andReturn([
                'data' => [[
                    'item_id' => 601,
                    'location_stocks' => [[
                        'location_id' => 99,
                        'on_hand' => 1,
                        'on_order' => 0,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    expect(JubelioStockCheck::latest()->first()->discrepancies()->count())->toBe(0);
});
