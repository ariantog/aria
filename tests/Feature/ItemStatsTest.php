<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\ItemStatsService;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

function seedItemStatLine(
    Item $item,
    Addrbook $warehouse,
    Addrbook $customer,
    int $type,
    string $date,
    float $qty,
    float $lineTotal,
    float $headerDiscount = 0,
): Transaction {
    $sender = $type === Transaction::TYPE_SELL ? $warehouse : $customer;
    $receiver = $type === Transaction::TYPE_SELL ? $customer : $warehouse;

    $transaction = Transaction::factory()->create([
        'type' => $type,
        'date' => $date,
        'discount' => $headerDiscount,
        'sender_id' => $sender->id,
        'sender_type' => (string) $sender->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $date,
        'transaction_type' => $type,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'quantity' => $qty,
        'price' => $qty > 0 ? $lineTotal / $qty : 0,
        'discount' => 0,
        'total' => $lineTotal,
    ]);

    return $transaction;
}

it('renders item stats from live sell and return lines without monthly-stat cache', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Utama']);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['name' => 'Stats SKU', 'code' => 'STAT-01']);

    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_SELL, now()->toDateString(), 2, 20000, 10);
    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_RETURN, now()->toDateString(), 1, 5000, 0);

    $this->actingAs($this->user)
        ->get(route('items.stats', ['item' => $item, 'period' => 90]))
        ->assertOk()
        ->assertSee('Item Statistics', false)
        ->assertSee('Stats SKU', false)
        ->assertSee('Sold qty', false)
        ->assertSee(now()->format('F Y'), false)
        ->assertSee('data-testid="item-stats-table"', false)
        ->assertSee('18,000', false)
        ->assertSee('5,000', false)
        ->assertDontSee('No cached stats yet', false);
});

it('renders asset lancar stats from live transaction details', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'name' => 'Asset Stats SKU',
        'code' => 'AST-01',
    ]);

    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_SELL, now()->toDateString(), 3, 30000);

    $this->actingAs($this->user)
        ->get(route('assetlancar.stats', ['item' => $item, 'period' => 90]))
        ->assertOk()
        ->assertSee('Item Statistics', false)
        ->assertSee('Asset Stats SKU', false)
        ->assertSee('/assetlancar/'.$item->id.'/stats', false)
        ->assertSee(now()->format('F Y'), false)
        ->assertSee('30,000', false);
});

it('excludes sell lines older than the selected period', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['name' => 'Period SKU']);

    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_SELL, now()->toDateString(), 2, 20000);
    seedItemStatLine(
        $item,
        $warehouse,
        $customer,
        Transaction::TYPE_SELL,
        now()->subMonths(8)->toDateString(),
        9,
        90000,
    );

    $this->actingAs($this->user)
        ->get(route('items.stats', ['item' => $item, 'period' => 90]))
        ->assertOk()
        ->assertSee('20,000', false)
        ->assertDontSee('90,000', false);
});

it('filters item stats by warehouse party', function () {
    $gudangA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang A']);
    $gudangB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang B']);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['name' => 'Warehouse SKU']);

    seedItemStatLine($item, $gudangA, $customer, Transaction::TYPE_SELL, now()->toDateString(), 2, 20000);
    seedItemStatLine($item, $gudangB, $customer, Transaction::TYPE_SELL, now()->toDateString(), 7, 70000);

    $this->actingAs($this->user)
        ->get(route('items.stats', ['item' => $item, 'period' => 90, 'warehouse_id' => $gudangA->id]))
        ->assertOk()
        ->assertSee('20,000', false)
        ->assertDontSee('70,000', false);
});

it('shows an empty state when the item has no sell or return lines', function () {
    $item = Item::factory()->create(['name' => 'Empty SKU']);

    $this->actingAs($this->user)
        ->get(route('items.stats', $item))
        ->assertOk()
        ->assertSee('No sell or return lines in this period.', false)
        ->assertSee('No statistical data available for this period.', false);
});

it('calculates monthly totals after invoice header discount', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_SELL, now()->toDateString(), 4, 40000, 25);
    seedItemStatLine($item, $warehouse, $customer, Transaction::TYPE_RETURN, now()->toDateString(), 1, 8000, 0);

    $stats = app(ItemStatsService::class)->monthlyBreakdown($item->id, 90);

    expect($stats['has_data'])->toBeTrue()
        ->and($stats['totals']['sold_qty'])->toBe(4.0)
        ->and($stats['totals']['returned_qty'])->toBe(1.0)
        ->and($stats['totals']['net_qty'])->toBe(3.0)
        ->and($stats['totals']['sold_value'])->toBe(30000.0)
        ->and($stats['totals']['returned_value'])->toBe(8000.0)
        ->and($stats['totals']['net_value'])->toBe(22000.0);
});
