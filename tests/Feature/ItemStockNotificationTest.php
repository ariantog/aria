<?php

use App\Enums\AddrbookType;
use App\Enums\ItemStockSourceStatus;
use App\Jobs\CheckItemStockNotifications;
use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemStockNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\ItemStockNotificationService;
use App\Services\PermissionGenerator;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('ItemStockNotification');
    Permission::firstOrCreate(['name' => ItemStockNotification::getPermissions()['view'], 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => ItemStockNotification::getPermissions()['dismiss'], 'guard_name' => 'web']);
    $this->user->givePermissionTo([
        ItemStockNotification::getPermissions()['view'],
        ItemStockNotification::getPermissions()['dismiss'],
    ]);
});

it('creates notifications when a sell depletes stock at one warehouse but stock remains elsewhere', function () {
    $shopA = Addrbook::factory()->warehouse()->create([
        'name' => 'Consignment Stylo',
        'arrangement_enabled' => true,
    ]);
    $shopB = Addrbook::factory()->warehouse()->create(['name' => 'Consignment Matahari']);
    $customer = Addrbook::factory()->customer()->create();

    $item = Item::factory()->create(['code' => 'TEST-SKU-M']);

    WarehouseItem::create([
        'warehouse_id' => $shopA->id,
        'item_id' => $item->id,
        'quantity' => 0,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $shopB->id,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_type' => (string) AddrbookType::Warehouse->value,
        'sender_id' => $shopA->id,
        'receiver_type' => (string) AddrbookType::Customer->value,
        'receiver_id' => $customer->id,
        'user_id' => $this->user->id,
    ]);

    $transaction->details()->create([
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $shopA->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 100000,
        'discount' => 0,
        'total' => 100000,
    ]);

    $ids = app(ItemStockNotificationService::class)->checkAfterSell($transaction->fresh('details'));

    expect($ids)->toHaveCount(1);

    $notification = ItemStockNotification::query()->first();
    expect($notification->item_id)->toBe($item->id)
        ->and($notification->sold_out_warehouse_id)->toBe($shopA->id)
        ->and($notification->source_warehouse_id)->toBe($shopB->id)
        ->and((float) $notification->source_stock)->toBe(5.0)
        ->and($notification->source_status)->toBe(ItemStockSourceStatus::DeadStock);
});

it('classifies slow moving stock at the source warehouse', function () {
    $shopA = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);
    $shopB = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $shopA->id,
        'item_id' => $item->id,
        'quantity' => 0,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $shopB->id,
        'item_id' => $item->id,
        'quantity' => 3,
    ]);

    // Old sale at source — sale 45 days ago → slow moving, not dead stock
    $oldSell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => now()->subDays(45)->toDateString(),
        'sender_type' => (string) AddrbookType::Warehouse->value,
        'sender_id' => $shopB->id,
        'receiver_type' => (string) AddrbookType::Customer->value,
        'receiver_id' => $customer->id,
        'user_id' => $this->user->id,
    ]);

    $oldSell->details()->create([
        'item_id' => $item->id,
        'date' => $oldSell->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $shopB->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 50000,
        'discount' => 0,
        'total' => 50000,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_type' => (string) AddrbookType::Warehouse->value,
        'sender_id' => $shopA->id,
        'receiver_type' => (string) AddrbookType::Customer->value,
        'receiver_id' => $customer->id,
        'user_id' => $this->user->id,
    ]);

    $sell->details()->create([
        'item_id' => $item->id,
        'date' => $sell->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $shopA->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 100000,
        'discount' => 0,
        'total' => 100000,
    ]);

    app(ItemStockNotificationService::class)->checkAfterSell($sell->fresh('details'));

    expect(ItemStockNotification::query()->first()?->source_status)
        ->toBe(ItemStockSourceStatus::SlowMoving);
});

it('skips alerts when the sold-out warehouse is not arrangement-enabled', function () {
    $customShop = Addrbook::factory()->warehouse()->create([
        'name' => 'Custom Only Shop',
        'arrangement_enabled' => false,
    ]);
    $otherShop = Addrbook::factory()->warehouse()->create(['name' => 'Consignment Shop']);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $customShop->id,
        'item_id' => $item->id,
        'quantity' => 0,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $otherShop->id,
        'item_id' => $item->id,
        'quantity' => 4,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'sender_type' => (string) AddrbookType::Warehouse->value,
        'sender_id' => $customShop->id,
        'receiver_type' => (string) AddrbookType::Customer->value,
        'receiver_id' => $customer->id,
        'user_id' => $this->user->id,
    ]);

    $transaction->details()->create([
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $customShop->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 100000,
        'discount' => 0,
        'total' => 100000,
    ]);

    $ids = app(ItemStockNotificationService::class)->checkAfterSell($transaction->fresh('details'));

    expect($ids)->toBeEmpty()
        ->and(ItemStockNotification::query()->count())->toBe(0);
});

it('dispatches stock notification check after sell summary update', function () {
    Queue::fake();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    (new UpdateTransactionSummaries($transaction->id))->handle(app(\App\Services\WarehouseItemStatsRecorder::class));

    Queue::assertPushed(CheckItemStockNotifications::class, function ($job) use ($transaction) {
        return $job->transactionId === $transaction->id;
    });
});

it('renders the stock notifications page for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('stock-notifications.index'))
        ->assertOk()
        ->assertSee('Stock Alerts', false);
});

it('forbids stock notifications page without permission', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('stock-notifications.index'))
        ->assertForbidden();
});

it('marks notifications read and dismissed', function () {
    $notification = ItemStockNotification::query()->create([
        'item_id' => Item::factory()->create()->id,
        'sold_out_warehouse_id' => Addrbook::factory()->warehouse()->create()->id,
        'source_warehouse_id' => Addrbook::factory()->warehouse()->create()->id,
        'source_stock' => 2,
        'source_status' => ItemStockSourceStatus::Available,
    ]);

    $this->actingAs($this->user)
        ->post(route('stock-notifications.read', $notification))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->post(route('stock-notifications.dismiss', $notification))
        ->assertRedirect();

    expect($notification->fresh()->dismissed_at)->not->toBeNull();
});
