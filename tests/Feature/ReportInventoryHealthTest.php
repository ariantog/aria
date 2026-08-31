<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => Report::getPermissions()['view-inventory-health'], 'guard_name' => 'web']);
    $this->user->givePermissionTo(Report::getPermissions()['view-inventory-health']);

    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Health Warehouse']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'Health Customer']);
});

function healthItem(string $name, string $code): Item
{
    return Item::factory()->create([
        'name' => $name,
        'code' => $code,
    ]);
}

function healthStock(Item $item, Addrbook $warehouse, float $qty): void
{
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => $qty,
    ]);
}

function healthLine(
    User $user,
    Addrbook $sender,
    Addrbook $receiver,
    Item $item,
    int $type,
    float $qty,
    string $date,
    string $invoice,
    int $status = Transaction::STATUS_COMPLETED,
): Transaction {
    $transaction = Transaction::factory()->create([
        'type' => $type,
        'invoice' => $invoice,
        'date' => $date,
        'status' => $status,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'user_id' => $user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'transaction_type' => $type,
        'date' => $date,
        'quantity' => $qty,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    return $transaction;
}

it('forbids users without the inventory health permission', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('reports.inventory-health'))
        ->assertForbidden();
});

it('renders export-sell style filters for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Inventory Health', false)
        ->assertSee('data-testid="inventory-health-page"', false)
        ->assertSee('data-testid="toggle-export-sell-filters"', false)
        ->assertSee('data-testid="export-sell-sender-combobox"', false)
        ->assertSee('data-testid="export-sell-receiver-combobox"', false)
        ->assertSee('data-testid="export-sell-item-combobox"', false)
        ->assertSee('data-testid="inventory-health-status"', false)
        ->assertSee('data-testid="inventory-health-sort-name"', false)
        ->assertSee('data-testid="inventory-health-sort-stock"', false)
        ->assertSee('sort=stock', false)
        ->assertSee('Net (Sell − Return)', false)
        ->assertSee('value="100"', false);
});

it('sorts rows by product name ascending by default', function () {
    $zebra = healthItem('Zebra Health Sku', 'HLTH-ZEBRA');
    $alpha = healthItem('Alpha Health Sku', 'HLTH-ALPHA');
    healthStock($zebra, $this->warehouse, 4);
    healthStock($alpha, $this->warehouse, 4);

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'Alpha Health Sku'))->toBeLessThan(strpos($html, 'Zebra Health Sku'));
});

it('sorts rows by stock descending when requested', function () {
    $low = healthItem('Low Stock Sort Sku', 'HLTH-SORT-LOW');
    $high = healthItem('High Stock Sort Sku', 'HLTH-SORT-HIGH');
    healthStock($low, $this->warehouse, 2);
    healthStock($high, $this->warehouse, 20);

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['sort' => 'stock', 'direction' => 'desc']))
        ->assertOk()
        ->assertSee('data-testid="inventory-health-sort-stock"', false)
        ->getContent();

    expect(strpos($html, 'High Stock Sort Sku'))->toBeLessThan(strpos($html, 'Low Stock Sort Sku'));
});

it('ignores unknown sort columns and keeps name order', function () {
    $zebra = healthItem('Zebra Unknown Sort', 'HLTH-UNK-Z');
    $alpha = healthItem('Alpha Unknown Sort', 'HLTH-UNK-A');
    healthStock($zebra, $this->warehouse, 3);
    healthStock($alpha, $this->warehouse, 3);

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['sort' => 'not-a-column', 'direction' => 'sideways']))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'Alpha Unknown Sort'))->toBeLessThan(strpos($html, 'Zebra Unknown Sort'));
});

it('does not call a single recent sale healthy when stock cover is too low', function () {
    $item = healthItem('Low Cover Tee', 'HLTH-LOW');
    healthStock($item, $this->warehouse, 2);
    healthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        20,
        now()->subDays(5)->toDateString(),
        'HLTH-LOW-1',
    );

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Low Cover Tee', false)
        ->getContent();

    expect($html)->toMatch('/data-testid="inventory-health-status-'.$item->id.'"[^>]*>Fast Moving \/ Low Stock/')
        ->and($html)->not->toMatch('/data-testid="inventory-health-status-'.$item->id.'"[^>]*>Healthy/');
});

it('nets returns out of sell quantity before classifying', function () {
    $item = healthItem('Returned Jacket', 'HLTH-NET');
    healthStock($item, $this->warehouse, 8);
    healthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        10,
        now()->subDays(4)->toDateString(),
        'HLTH-NET-SELL',
    );
    healthLine(
        $this->user,
        $this->customer,
        $this->warehouse,
        $item,
        Transaction::TYPE_RETURN,
        10,
        now()->subDays(2)->toDateString(),
        'HLTH-NET-RET',
    );

    $html = $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Returned Jacket', false)
        ->getContent();

    expect($html)->toMatch('/data-testid="inventory-health-status-'.$item->id.'"[^>]*>Dead Stock/')
        ->and($html)->not->toMatch('/data-testid="inventory-health-status-'.$item->id.'"[^>]*>Healthy/');
});

it('ignores pending sales when computing health', function () {
    $item = healthItem('Pending Only', 'HLTH-PEND');
    healthStock($item, $this->warehouse, 6);
    healthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        40,
        now()->subDays(3)->toDateString(),
        'HLTH-PEND-1',
        Transaction::STATUS_PENDING,
    );

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Pending Only', false)
        ->assertSee('Dead Stock', false);
});

it('filters rows to the selected warehouse sender', function () {
    $otherWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Other Health Warehouse']);
    $visible = healthItem('Visible Health SKU', 'HLTH-WH-A');
    $hidden = healthItem('Hidden Health SKU', 'HLTH-WH-B');
    healthStock($visible, $this->warehouse, 5);
    healthStock($hidden, $otherWarehouse, 5);
    healthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $visible,
        Transaction::TYPE_SELL,
        5,
        now()->subDays(6)->toDateString(),
        'HLTH-WH-A-1',
    );
    healthLine(
        $this->user,
        $otherWarehouse,
        $this->customer,
        $hidden,
        Transaction::TYPE_SELL,
        5,
        now()->subDays(6)->toDateString(),
        'HLTH-WH-B-1',
    );

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['sender' => $this->warehouse->id]))
        ->assertOk()
        ->assertSee('Visible Health SKU', false)
        ->assertDontSee('Hidden Health SKU', false);
});

it('filters rows to the selected item', function () {
    $match = healthItem('Filter Match SKU', 'HLTH-ITEM-A');
    $other = healthItem('Filter Other SKU', 'HLTH-ITEM-B');
    healthStock($match, $this->warehouse, 3);
    healthStock($other, $this->warehouse, 3);

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['item_id' => $match->id]))
        ->assertOk()
        ->assertSee('Filter Match SKU', false)
        ->assertDontSee('Filter Other SKU', false);
});

it('filters by classified status', function () {
    $dead = healthItem('Dead Health SKU', 'HLTH-DEAD');
    $fast = healthItem('Fast Health SKU', 'HLTH-FAST');
    healthStock($dead, $this->warehouse, 9);
    healthStock($fast, $this->warehouse, 1);
    healthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $fast,
        Transaction::TYPE_SELL,
        20,
        now()->subDays(2)->toDateString(),
        'HLTH-FAST-1',
    );

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health', ['status' => InventoryHealthClassifier::DEAD]))
        ->assertOk()
        ->assertSee('Dead Health SKU', false)
        ->assertDontSee('Fast Health SKU', false);
});
