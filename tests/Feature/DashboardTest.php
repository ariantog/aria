<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemStockNotification;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\JubelioStockCheck;
use App\Models\Jubelioreturn;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Models\WarehouseArrangementRefreshJob;
use App\Services\PermissionGenerator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('superadmin sees phase 1 dashboard widgets', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    Jubelioorder::create([
        'jubelio_order_id' => 'dash-pending-1',
        'source' => 1,
        'invoice' => 'INV-DASH-PENDING',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'dash-error-1',
        'source' => 1,
        'invoice' => 'INV-DASH-ERROR',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => 1,
    ]);

    $shop = Addrbook::factory()->warehouse()->create(['name' => 'Dash Shop', 'arrangement_enabled' => true]);
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Dash Source']);
    $item = Item::factory()->create(['code' => 'DASH-SKU']);

    ItemStockNotification::create([
        'item_id' => $item->id,
        'sold_out_warehouse_id' => $shop->id,
        'source_warehouse_id' => $source->id,
        'source_stock' => 3,
        'source_status' => 'available',
    ]);

    ScheduledTask::updateOrCreate(
        ['command' => 'app:process-queue'],
        [
            'name' => 'Process Queue Jobs',
            'frequency' => 'everyMinute',
            'active' => true,
            'description' => 'Test queue worker',
        ]
    );

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['job' => 'test']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-testid="dashboard-health-strip"', false)
        ->assertSee('data-testid="dashboard-jubelio-connection"', false)
        ->assertSee('data-testid="dashboard-queue-status"', false)
        ->assertSee('data-testid="dashboard-kpi-jubelio-pending"', false)
        ->assertSee('data-testid="dashboard-kpi-jubelio-error"', false)
        ->assertSee('data-testid="dashboard-kpi-stock-alerts"', false)
        ->assertSee('data-testid="dashboard-kpi-queue"', false)
        ->assertSee('data-testid="dashboard-stock-alerts-list"', false)
        ->assertSee('DASH-SKU', false)
        ->assertSee('1', false);
});

test('superadmin sees phase 2 dashboard widgets', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    Jubelioreturn::create([
        'order_id' => 'dash-cancel-1',
        'transaction_id' => '1',
        'invoice' => 'INV-CANCEL-DASH',
        'status' => 0,
        'confirmed_by' => 0,
    ]);

    $completedCheck = JubelioStockCheck::create([
        'sync_cursor' => 1,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'completed',
    ]);
    $item = Item::factory()->create(['jubelio_item_id' => 501]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $completedCheck->discrepancies()->create([
        'item_id' => $item->id,
        'jubelio_item_id' => 501,
        'jubelio_location_id' => 10,
        'warehouse_id' => $warehouse->id,
        'aria_qty' => 10,
        'jubelio_qty' => 5,
    ]);

    JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'status' => 'processing',
    ]);

    ScheduledTask::updateOrCreate(
        ['command' => 'shopee-ads:process'],
        [
            'name' => 'Disabled Test Cron',
            'frequency' => 'everyMinute',
            'active' => false,
            'description' => 'Disabled for dashboard test',
        ]
    );

    $refreshWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Dash Refresh WH']);
    WarehouseArrangementRefreshJob::create([
        'destination_warehouse_id' => $refreshWarehouse->id,
        'user_id' => $user->id,
        'status' => WarehouseArrangementRefreshJob::STATUS_PROCESSING,
        'phase' => WarehouseArrangementRefreshJob::PHASE_STATS,
        'item_cursor' => 5,
        'total_items' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-testid="dashboard-kpi-cancellations"', false)
        ->assertSee('data-testid="dashboard-kpi-stock-discrepancies"', false)
        ->assertSee('data-testid="dashboard-kpi-crons-disabled"', false)
        ->assertSee('data-testid="dashboard-kpi-book-closing"', false)
        ->assertSee('data-testid="dashboard-book-closing"', false)
        ->assertSee('data-testid="dashboard-stock-check-active"', false)
        ->assertSee('data-testid="dashboard-arrangement-refresh"', false)
        ->assertSee('data-testid="dashboard-arrangement-jobs"', false)
        ->assertSee('data-testid="dashboard-disabled-crons-list"', false)
        ->assertSee('Disabled Test Cron', false)
        ->assertSee('Dash Refresh WH', false);
});

test('users without ops permissions do not see the ops panel', function () {
    $user = User::factory()->create(['id' => 99]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-testid="dashboard-health-strip"', false)
        ->assertDontSee('data-testid="dashboard-kpi-jubelio-pending"', false)
        ->assertDontSee('data-testid="dashboard-stock-alerts-list"', false);
});

test('jubelio viewers see connection and order widgets without cron queue card', function () {
    $user = User::factory()->create(['id' => 99]);
    app(PermissionGenerator::class)->generateForModule('Jubelio');
    Permission::firstOrCreate(['name' => Jubelio::getPermissions()['view'], 'guard_name' => 'web']);
    $user->givePermissionTo(Jubelio::getPermissions()['view']);

    Jubelioorder::create([
        'jubelio_order_id' => 'dash-jub-only',
        'source' => 1,
        'invoice' => 'INV-JUB-ONLY',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-testid="dashboard-jubelio-connection"', false)
        ->assertSee('data-testid="dashboard-kpi-jubelio-pending"', false)
        ->assertSee('data-testid="dashboard-queue-status"', false)
        ->assertDontSee('data-testid="dashboard-kpi-queue"', false)
        ->assertDontSee('data-testid="dashboard-stock-alerts-list"', false);
});

test('stock alert viewers see unread list without jubelio widgets', function () {
    $user = User::factory()->create(['id' => 98]);
    app(PermissionGenerator::class)->generateForModule('ItemStockNotification');
    Permission::firstOrCreate(['name' => ItemStockNotification::getPermissions()['view'], 'guard_name' => 'web']);
    $user->givePermissionTo(ItemStockNotification::getPermissions()['view']);

    $shop = Addrbook::factory()->warehouse()->create(['name' => 'Alert Shop', 'arrangement_enabled' => true]);
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Alert Source']);
    $item = Item::factory()->create(['code' => 'ALERT-SKU']);

    ItemStockNotification::create([
        'item_id' => $item->id,
        'sold_out_warehouse_id' => $shop->id,
        'source_warehouse_id' => $source->id,
        'source_stock' => 2,
        'source_status' => 'slow_moving',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-testid="dashboard-kpi-stock-alerts"', false)
        ->assertSee('data-testid="dashboard-stock-alerts-list"', false)
        ->assertSee('ALERT-SKU', false)
        ->assertDontSee('data-testid="dashboard-jubelio-connection"', false)
        ->assertDontSee('data-testid="dashboard-kpi-jubelio-pending"', false);
});
