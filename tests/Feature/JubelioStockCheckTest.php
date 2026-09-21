<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\Jubeliosync;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use App\Services\JubelioStockCheckService;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

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

it('compares aria qty against jubelio available stock', function () {
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
                        'on_hand' => 55,
                        'on_order' => 5,
                        'reserved' => 5,
                        'available' => 50,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    $discrepancy = $job->fresh()->discrepancies()->first();
    expect($discrepancy)->toBeNull();
    expect($job->fresh()->status)->toBe('completed');
});

it('flags a mismatch using available even when on-hand differs', function () {
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
                        'on_hand' => 51,
                        'on_order' => 0,
                        'reserved' => 0,
                        'available' => 40,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    $discrepancy = $job->fresh()->discrepancies()->first();
    expect($discrepancy)->not->toBeNull();
    expect((float) $discrepancy->aria_qty)->toBe(50.0);
    expect((float) $discrepancy->jubelio_on_hand)->toBe(51.0);
    expect((float) $discrepancy->jubelio_available)->toBe(40.0);
    expect((float) $discrepancy->jubelio_qty)->toBe(40.0);
});

it('flags webhook lag when aria is still 10 but jubelio available dropped to 9', function () {
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
        'quantity' => 10,
    ]);

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
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
                        'on_hand' => 10,
                        'on_order' => 1,
                        'reserved' => 1,
                        'available' => 9,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    $discrepancy = $job->fresh()->discrepancies()->first();
    expect($discrepancy)->not->toBeNull();
    expect((float) $discrepancy->aria_qty)->toBe(10.0);
    expect((float) $discrepancy->jubelio_qty)->toBe(9.0);
    expect($discrepancy->qty_diff)->toBe(1.0);
});

it('on-hand comparison would miss webhook lag when on-hand has not dropped yet', function () {
    $service = app(JubelioStockCheckService::class);

    $quantities = $service->resolveLocationQuantities([
        'on_hand' => 10,
        'on_order' => 1,
        'reserved' => 1,
        'available' => 9,
    ]);

    $ariaQty = 10.0;
    $onHandMatch = $ariaQty === $quantities['on_hand'];
    $availableMismatch = $ariaQty !== $quantities['comparable'];

    expect($onHandMatch)->toBeTrue();
    expect($availableMismatch)->toBeTrue();
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
                    'location_stocks' => [['location_id' => 10, 'on_hand' => 1, 'on_order' => 0, 'reserved' => 0, 'available' => 1]],
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
                    'location_stocks' => [['location_id' => 20, 'on_hand' => 1, 'on_order' => 0, 'reserved' => 0, 'available' => 1]],
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
                        'reserved' => 0,
                        'available' => 1,
                    ]],
                ]],
            ]);
    });

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    expect(JubelioStockCheck::latest()->first()->discrepancies()->count())->toBe(0);
});

it('batches jubelio all-stocks requests at 200 ids per call', function () {
    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'test-token',
            'expires_at' => now()->addHour()->toDateTimeString(),
        ],
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), 'inventory/items/all-stocks')) {
            return Http::response([], 404);
        }

        $payload = json_decode($request->body(), true);
        $ids = $payload['ids'] ?? [];

        expect(count($ids))->toBeLessThanOrEqual(JubelioService::MAX_ALL_STOCKS_IDS);

        return Http::response([
            'data' => collect($ids)->map(fn ($id) => [
                'item_id' => $id,
                'location_stocks' => [],
            ])->all(),
        ], 200);
    });

    $result = app(JubelioService::class)->fetchItemsAllStocks(range(1, 250));

    expect($result['data'])->toHaveCount(250);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'inventory/items/all-stocks');
    }, 2);
});

it('records cron heartbeat even when no stock check job is due', function () {
    ScheduledTask::create([
        'name' => 'Jubelio Stock Check',
        'command' => 'app:jubelio-stock-check',
        'frequency' => 'everyMinute',
        'active' => true,
        'description' => 'test',
    ]);

    JubelioStockCheck::create([
        'sync_cursor' => 1,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'completed',
        'created_at' => now(),
    ]);

    $this->artisan('app:jubelio-stock-check')->assertSuccessful();

    expect(ScheduledTask::query()->where('command', 'app:jubelio-stock-check')->value('last_run_at'))->not->toBeNull();
});

it('dispatches legacy cron manager command with --single flag', function () {
    ScheduledTask::create([
        'name' => 'Jubelio Stock Check',
        'command' => 'app:jubelio-stock-check --single',
        'frequency' => 'everyMinute',
        'active' => true,
        'description' => 'test',
    ]);

    JubelioStockCheck::create([
        'sync_cursor' => 1,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'completed',
        'created_at' => now(),
    ]);

    $this->artisan('app:dispatch-scheduled-tasks')->assertSuccessful();

    expect(ScheduledTask::query()->where('command', 'app:jubelio-stock-check --single')->value('last_run_at'))->not->toBeNull();
});

it('recovers stale processing jobs so a new daily check can start', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($warehouse);

    $staleJob = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'processing',
    ]);
    $staleJob->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subHours(6),
    ])->saveQuietly();

    $service = app(JubelioStockCheckService::class);
    $job = $service->ensureDailyJob();

    expect($staleJob->fresh()->status)->toBe('completed');
    expect($job)->not->toBeNull();
    expect($job->id)->not->toBe($staleJob->id);
});

it('auto-creates a daily stock check job when none exists today', function () {
    $service = app(JubelioStockCheckService::class);

    $job = $service->ensureDailyJob();

    expect($job)->not->toBeNull();
    expect($job->target_discrepancies)->toBe(JubelioStockCheckService::DEFAULT_TARGET_DISCREPANCIES);
    expect($service->ensureDailyJob()?->id)->toBe($job->id);
});

it('starts another scan round when target discrepancies are not met', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedStockCheckSync($warehouse);

    $items = collect();
    for ($i = 0; $i < 3; $i++) {
        $item = Item::factory()->create([
            'type' => Item::TYPE_ITEM,
            'jubelio_item_id' => 801 + $i,
        ]);
        $items->push($item);
        WarehouseItem::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => Addrbook::class,
            'quantity' => 10,
        ]);
    }

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 1,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'processing',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->andReturnUsing(function (array $ids) {
                return [
                    'data' => collect($ids)->map(fn ($id) => [
                        'item_id' => $id,
                        'location_stocks' => [[
                            'location_id' => 10,
                            'on_hand' => 9,
                            'on_order' => 0,
                            'reserved' => 0,
                            'available' => 9,
                        ]],
                    ])->all(),
                ];
            });
    });

    $service = app(JubelioStockCheckService::class);
    $service->processNextWarehouse($job);
    $job->refresh();
    $service->processNextWarehouse($job);
    $job->refresh();

    expect($job->scan_round)->toBe(1)
        ->and($job->sync_cursor)->toBe(0)
        ->and($job->status)->toBe('processing');
});

it('sorts stock check discrepancies by absolute quantity difference', function () {
    Permission::firstOrCreate(['name' => 'jubelio-stock-check']);
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-stock-check');

    $job = JubelioStockCheck::create([
        'sync_cursor' => 1,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'completed',
    ]);

    $smallDiff = Item::factory()->create(['code' => 'SKU-SMALL-DIFF']);
    $bigDiff = Item::factory()->create(['code' => 'SKU-BIG-DIFF']);
    $mediumDiff = Item::factory()->create(['code' => 'SKU-MED-DIFF']);

    $job->discrepancies()->createMany([
        [
            'item_id' => $smallDiff->id,
            'jubelio_item_id' => 1,
            'jubelio_location_id' => 10,
            'jubelio_location_name' => 'Gudang',
            'warehouse_id' => 1,
            'aria_qty' => 10,
            'jubelio_qty' => 9,
            'jubelio_on_hand' => 10,
            'jubelio_on_order' => 0,
            'jubelio_available' => 9,
            'jubelio_reserved' => 1,
        ],
        [
            'item_id' => $bigDiff->id,
            'jubelio_item_id' => 2,
            'jubelio_location_id' => 10,
            'jubelio_location_name' => 'Gudang',
            'warehouse_id' => 1,
            'aria_qty' => 100,
            'jubelio_qty' => 50,
            'jubelio_on_hand' => 50,
            'jubelio_on_order' => 0,
            'jubelio_available' => 50,
            'jubelio_reserved' => 0,
        ],
        [
            'item_id' => $mediumDiff->id,
            'jubelio_item_id' => 3,
            'jubelio_location_id' => 10,
            'jubelio_location_name' => 'Gudang',
            'warehouse_id' => 1,
            'aria_qty' => 5,
            'jubelio_qty' => 7,
            'jubelio_on_hand' => 7,
            'jubelio_on_order' => 0,
            'jubelio_available' => 7,
            'jubelio_reserved' => 0,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('jubelio-stock-checks.show', $job->id))
        ->assertSuccessful()
        ->assertSeeInOrder(['SKU-BIG-DIFF', 'SKU-MED-DIFF', 'SKU-SMALL-DIFF']);
});

it('stores a stock check job with the warehouse cursor columns', function () {
    Permission::firstOrCreate(['name' => 'jubelio-stock-check']);
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-stock-check');

    $this->actingAs($user)
        ->post(route('jubelio-stock-checks.store'), [
            'per_type_limit' => 50,
            'demand_days' => 90,
            'target_discrepancies' => 50,
        ])
        ->assertRedirect(route('jubelio-stock-checks.index'));

    $job = JubelioStockCheck::query()->latest('id')->first();

    expect($job)->not->toBeNull()
        ->and($job->sync_cursor)->toBe(0)
        ->and($job->per_type_limit)->toBe(50)
        ->and($job->demand_days)->toBe(90)
        ->and($job->target_discrepancies)->toBe(50)
        ->and($job->scan_round)->toBe(0)
        ->and($job->status)->toBe('created');
});
