<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\StockData;
use App\Models\StokReport;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

it('can generate stock intelligence report', function () {
    // 1. Setup Data
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create();

    // Create stock
    DB::table('warehouse_items')->insert([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create a transaction first to satisfy foreign key
    $transaction = Transaction::factory()->create();

    // Create a sale 10 days ago
    DB::table('transaction_details')->insert([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'sender_id' => $warehouse->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'quantity' => 1,
        'price' => 1000,
        'discount' => 0,
        'total' => 1000,
        'date' => now()->subDays(10)->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Run Command
    $this->artisan('app:generate-stock-intelligence')
        ->expectsOutput('Starting stock intelligence generation...')
        ->expectsOutput('Stock intelligence generation completed successfully.')
        ->assertSuccessful();

    // 3. Assertions
    expect(StokReport::count())->toBe(1);
    $report = StokReport::first();
    expect($report->type)->toBe('cron');

    expect(StockData::count())->toBe(1);
    $data = StockData::first();
    expect($data->item_id)->toBe($item->id);
    expect($data->current_warehouse_id)->toBe($warehouse->id);
    expect($data->score)->toBeGreaterThan(0);
});

it('can display stock intelligence from database', function () {
    $user = \App\Models\User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'reports-inventory-health']);
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    $report = StokReport::create([
        'generet_at' => now(),
        'type' => 'cron',
    ]);

    $item = Item::factory()->create(['name' => 'Test Item']);
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE, 'name' => 'Test Warehouse']);

    StockData::create([
        'id_stock_report' => $report->id,
        'item_id' => $item->id,
        'item_name' => $item->name,
        'score' => 0.95,
        'performance_key' => 'elite',
        'performance_level' => '1. Elite',
        'gap_days' => 5,
        'current_warehouse_id' => $warehouse->id,
        'current_warehouse_name' => $warehouse->name,
        'current_warehouse_qty' => 100,
        'current_warehouse_last_sale' => '2026-04-01',
        'current_warehouse_days_ago' => 21,
    ]);

    $response = $this->get('/reports/stock-intelligence');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/StockIntelligence')
        ->has('data.data', 1)
        ->where('data.data.0.item_name', 'Test Item')
        ->where('stats.all', 1)
    );
});
