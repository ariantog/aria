<?php

use App\Models\Addrbook;
use App\Models\InventoryHealthSnapshot;
use App\Models\Item;
use App\Models\Report;
use App\Models\ScheduledTask;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\InventoryHealth\InventoryHealthSyncService;
use Database\Seeders\ScheduledTaskSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => Report::getPermissions()['view-inventory-health'], 'guard_name' => 'web']);
    $this->user->givePermissionTo(Report::getPermissions()['view-inventory-health']);

    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Snapshot Warehouse']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'Snapshot Customer']);
});

function healthStatItem(string $name, string $code): Item
{
    return Item::factory()->create([
        'name' => $name,
        'code' => $code,
    ]);
}

function healthMonthlyStat(Item $item, Addrbook $warehouse, float $sold, float $returned = 0, ?\Carbon\CarbonInterface $when = null): void
{
    $when ??= now();

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'month' => $when->month,
        'year' => $when->year,
        'sold_qty' => $sold,
        'returned_qty' => $returned,
    ]);
}

function healthOnHand(Item $item, Addrbook $warehouse, float $qty): void
{
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => $qty,
    ]);
}

function healthLiveLine(
    User $user,
    Addrbook $sender,
    Addrbook $receiver,
    Item $item,
    float $qty,
    string $invoice,
): void {
    $date = now()->subDays(3)->toDateString();
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $invoice,
        'date' => $date,
        'status' => Transaction::STATUS_COMPLETED,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'user_id' => $user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'date' => $date,
        'quantity' => $qty,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
}

it('rebuilds snapshots from monthly stats and current stock', function () {
    $item = healthStatItem('Cached Health Tee', 'SNAP-TEE');
    healthOnHand($item, $this->warehouse, 2);
    healthMonthlyStat($item, $this->warehouse, 20, 0);

    $result = app(InventoryHealthSyncService::class)->syncAll();

    expect($result['warehouses'])->toBe(1)
        ->and($result['rows'])->toBe(2);

    $company = InventoryHealthSnapshot::query()
        ->where('warehouse_id', InventoryHealthSyncService::COMPANY_WAREHOUSE_ID)
        ->where('item_id', $item->id)
        ->first();

    expect($company)->not->toBeNull()
        ->and((float) $company->sold_period)->toBe(20.0)
        ->and((float) $company->current_stock)->toBe(2.0);
});

it('serves the default report from snapshots without transaction details', function () {
    $item = healthStatItem('Snapshot Only Tee', 'SNAP-ONLY');
    healthOnHand($item, $this->warehouse, 2);
    healthMonthlyStat($item, $this->warehouse, 20, 0);

    $this->artisan('app:sync-inventory-health')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0);

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Snapshot Only Tee', false)
        ->assertSee('data-source="snapshot"', false)
        ->getContent();

    expect($html)->toMatch('/data-testid="inventory-health-status-'.$item->id.'"[^>]*>Fast Moving \/ Low Stock/');
});

it('keeps invoice and custom-date filters on the live transaction path', function () {
    $cached = healthStatItem('Cached Live Split', 'SNAP-CACHE');
    healthOnHand($cached, $this->warehouse, 8);
    healthMonthlyStat($cached, $this->warehouse, 4, 0);

    $liveOnly = healthStatItem('Invoice Only Sku', 'SNAP-LIVE');
    healthLiveLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $liveOnly,
        12,
        'SNAP-INV-99',
    );

    app(InventoryHealthSyncService::class)->syncAll();

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Cached Live Split', false)
        ->assertDontSee('Invoice Only Sku', false)
        ->assertSee('data-source="snapshot"', false);

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['invoice' => 'SNAP-INV-99']))
        ->assertOk()
        ->assertSee('Invoice Only Sku', false)
        ->assertSee('data-source="live"', false);
});

it('filters snapshot rows to the selected warehouse', function () {
    $other = Addrbook::factory()->warehouse()->create(['name' => 'Other Snapshot Warehouse']);
    $here = healthStatItem('Here Snapshot Sku', 'SNAP-WH-A');
    $there = healthStatItem('There Snapshot Sku', 'SNAP-WH-B');
    healthOnHand($here, $this->warehouse, 5);
    healthOnHand($there, $other, 5);
    healthMonthlyStat($here, $this->warehouse, 5, 0);
    healthMonthlyStat($there, $other, 5, 0);

    app(InventoryHealthSyncService::class)->syncAll();

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['sender' => $this->warehouse->id]))
        ->assertOk()
        ->assertSee('Here Snapshot Sku', false)
        ->assertDontSee('There Snapshot Sku', false)
        ->assertSee('data-source="snapshot"', false);
});

it('classifies snapshot rows with the cover rules', function () {
    $dead = healthStatItem('Dead Snapshot Sku', 'SNAP-DEAD');
    healthOnHand($dead, $this->warehouse, 9);

    $fast = healthStatItem('Fast Snapshot Sku', 'SNAP-FAST');
    healthOnHand($fast, $this->warehouse, 1);
    healthMonthlyStat($fast, $this->warehouse, 20, 0);

    app(InventoryHealthSyncService::class)->syncAll();

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['status' => InventoryHealthClassifier::DEAD]))
        ->assertOk()
        ->assertSee('Dead Snapshot Sku', false)
        ->assertDontSee('Fast Snapshot Sku', false);
});

it('seeds a daily inventory health sync and removes the unbounded rebuild', function () {
    ScheduledTask::create([
        'name' => 'Legacy Inventory Health',
        'command' => 'app:recalculate-inventory-health',
        'frequency' => 'weekly',
        'active' => true,
        'description' => 'unsafe',
    ]);

    $this->seed(ScheduledTaskSeeder::class);

    $task = ScheduledTask::query()
        ->where('command', 'app:sync-inventory-health')
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->frequency)->toBe('daily')
        ->and($task->active)->toBeTrue()
        ->and(
            ScheduledTask::query()->where('command', 'app:recalculate-inventory-health')->exists()
        )->toBeFalse();
});
