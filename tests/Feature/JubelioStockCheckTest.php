<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\Jubeliosync;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Mockery\MockInterface;

it('detects stock discrepancies between Aria and Jubelio', function () {
    // 1. Setup Data
    $item = Item::factory()->create(['jubelio_item_id' => 123]);
    $warehouse = Addrbook::factory()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store 1',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Gudang Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => 'App\\Models\\Addrbook',
        'quantity' => 50,
    ]);

    // 2. Mock Jubelio Service
    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchInventory')
            ->once()
            ->with(1, 200)
            ->andReturn([
                'data' => [
                    [
                        'item_id' => 123,
                        'location_stocks' => [
                            [
                                'location_id' => 10,
                                'on_hand' => 45, // Beda dengan Aria (50)
                            ],
                        ],
                    ],
                ],
            ]);
    });

    // 3. Run Command
    $this->artisan('app:jubelio-stock-check')
        ->expectsOutput('Memulai pengecekan stok Jubelio...')
        ->expectsOutput('Memproses halaman: 1...')
        ->assertExitCode(0);

    // 4. Assertions
    $job = JubelioStockCheck::latest()->first();
    expect($job->status)->toBe('completed');
    expect($job->page_tracking)->toBe(2);

    $discrepancy = $job->discrepancies()->first();
    expect($discrepancy->jubelio_item_id)->toBe(123);
    expect((float) $discrepancy->aria_qty)->toBe(50.0);
    expect((float) $discrepancy->jubelio_qty)->toBe(45.0);
});

it('stops when reaching 200 discrepancies', function () {
    // 1. Setup Data
    $warehouse = Addrbook::factory()->create();

    Jubeliosync::create([
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Test Location',
        'warehouse_id' => $warehouse->id,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Test Store',
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    $itemsData = [];
    for ($i = 1; $i <= 201; $i++) {
        $item = Item::factory()->create(['jubelio_item_id' => $i]);
        WarehouseItem::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => 'App\\Models\\Addrbook',
            'quantity' => 100,
        ]);

        $itemsData[] = [
            'item_id' => $i,
            'location_stocks' => [
                ['location_id' => 10, 'on_hand' => 90], // Selalu beda
            ],
        ];
    }

    // 2. Mock Jubelio Service to return 201 items (first 200 will trigger the limit)
    $this->mock(JubelioService::class, function (MockInterface $mock) use ($itemsData) {
        $mock->shouldReceive('fetchInventory')
            ->once()
            ->andReturn(['data' => $itemsData]);
    });

    // 3. Run Command
    $this->artisan('app:jubelio-stock-check')
        ->assertExitCode(0);

    // 4. Assertions
    $job = JubelioStockCheck::latest()->first();
    expect($job->status)->toBe('stopped');
    expect($job->discrepancies()->count())->toBe(200);
});
