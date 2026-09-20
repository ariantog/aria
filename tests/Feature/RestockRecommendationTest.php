<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\ItemInsightSyncService;
use App\Services\Restock\RestockRecommendationService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

function restockHealthItem(string $name, string $code, ItemType $type = ItemType::ITEM): Item
{
    return Item::factory()->create([
        'name' => $name,
        'code' => $code,
        'type' => $type,
    ]);
}

function restockHealthStock(Item $item, Addrbook $warehouse, float $qty): void
{
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => $qty,
    ]);
}

function restockHealthLine(
    User $user,
    Addrbook $sender,
    Addrbook $receiver,
    Item $item,
    int $type,
    float $qty,
    string $date,
    string $invoice,
): Transaction {
    $transaction = Transaction::factory()->create([
        'type' => $type,
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
        'transaction_type' => $type,
        'date' => $date,
        'quantity' => $qty,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    return $transaction;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'restock-list', 'guard_name' => 'web']);
    $this->user->givePermissionTo('restock-list');

    $this->warehouse = Addrbook::factory()->warehouse()->create();
    $this->customer = Addrbook::factory()->customer()->create();
});

it('lists fast-moving low-stock recommendations from inventory health rules', function () {
    $this->travelTo('2026-05-15');
    $item = restockHealthItem('Hot Low Stock', 'HOT-LOW');
    restockHealthStock($item, $this->warehouse, 2);
    restockHealthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        40,
        now()->subDays(5)->toDateString(),
        'HOT-LOW-1',
    );

    $service = app(RestockRecommendationService::class);
    $payload = $service->build(Request::create('/restock/recommendations'), $this->user);

    $row = collect($payload['fast_moving']->items())->firstWhere('item_id', $item->id);
    expect($row)->not->toBeNull();
    expect($row['health_key'])->toBe(InventoryHealthClassifier::LOW);
    expect($row['worth']['pattern'])->toBeString();
});

it('lists high-margin recommendations from item insights with health guardrails', function () {
    $this->travelTo('2026-04-30');
    $item = restockHealthItem('Margin SKU', 'MARGIN-1');
    $item->update(['cost' => 1000]);
    restockHealthStock($item, $this->warehouse, 8);
    restockHealthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        20,
        '2026-04-15',
        'MARGIN-1',
    );

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $this->warehouse->id,
        'item_id' => $item->id,
        'year' => 2026,
        'month' => 4,
        'sold_qty' => 20,
        'returned_qty' => 0,
        'sold_value' => 100_000,
        'returned_value' => 0,
    ]);

    app(ItemInsightSyncService::class)->recalculateMonth(2026, 4);

    $service = app(RestockRecommendationService::class);
    $payload = $service->build(Request::create('/restock/recommendations'), $this->user);

    $row = collect($payload['high_margin']->items())->firstWhere('item_id', $item->id);
    expect($row)->not->toBeNull();
    expect($row['margin_pct'])->toBeGreaterThan(25.0);
});

it('filters recommendations by item type', function () {
    $this->travelTo('2026-05-15');
    $manufactured = restockHealthItem('Mfg Low', 'MFG-LOW', ItemType::ITEM);
    $asset = restockHealthItem('Asset Low', 'AST-LOW', ItemType::ASSET_LANCAR);

    foreach ([$manufactured, $asset] as $item) {
        restockHealthStock($item, $this->warehouse, 1);
        restockHealthLine(
            $this->user,
            $this->warehouse,
            $this->customer,
            $item,
            Transaction::TYPE_SELL,
            30,
            now()->subDays(3)->toDateString(),
            $item->code.'-INV',
        );
    }

    $service = app(RestockRecommendationService::class);
    $all = $service->build(Request::create('/restock/recommendations'), $this->user);
    $assetsOnly = $service->build(
        Request::create('/restock/recommendations?item_type='.ItemType::ASSET_LANCAR->value),
        $this->user,
    );

    expect(collect($all['fast_moving']->items())->pluck('item_id'))
        ->toContain($manufactured->id, $asset->id);
    expect(collect($assetsOnly['fast_moving']->items())->pluck('item_id'))
        ->toContain($asset->id)
        ->not->toContain($manufactured->id);
});

it('shows restock sheet pipeline qty on recommendation rows', function () {
    $this->travelTo('2026-05-15');
    $item = restockHealthItem('Pipeline SKU', 'PIPE-1', ItemType::ASSET_LANCAR);
    restockHealthStock($item, $this->warehouse, 1);
    restockHealthLine(
        $this->user,
        $this->warehouse,
        $this->customer,
        $item,
        Transaction::TYPE_SELL,
        50,
        now()->subDays(3)->toDateString(),
        'PIPE-1-INV',
    );

    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'PIPE',
        'name' => 'Pipe',
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);
    $sheet = RestockSheet::create([
        'name' => 'Pipe',
        'type_tag_id' => $typeTag->id,
        'created_by' => $this->user->id,
    ]);
    RestockCell::create([
        'restock_sheet_id' => $sheet->id,
        'item_id' => $item->id,
        'qty_restock' => 7,
        'qty_production' => 8,
        'qty_shipped' => 9,
    ]);

    $row = collect(app(RestockRecommendationService::class)
        ->build(Request::create('/restock/recommendations'), $this->user)['fast_moving']
        ->items())
        ->firstWhere('item_id', $item->id);

    expect($row)->not->toBeNull();
    expect($row['qty_restock'])->toBe(7)
        ->and($row['qty_production'])->toBe(8)
        ->and($row['qty_shipped'])->toBe(9);
});

it('renders the restock recommendations page', function () {
    $this->actingAs($this->user)
        ->get(route('restock.recommendations'))
        ->assertOk()
        ->assertSee('data-testid="restock-recommendations-page"', false)
        ->assertSee('data-testid="restock-recommendations-tab-hero"', false)
        ->assertSee('data-testid="restock-recommendations-tab-margin"', false)
        ->assertSee('data-testid="restock-recommendations-type-all"', false)
        ->assertSee('data-testid="restock-recommendations-type-'.ItemType::ITEM->value.'"', false)
        ->assertSee('data-testid="restock-recommendations-type-'.ItemType::ASSET_LANCAR->value.'"', false);
});

it('forbids restock recommendations without permission', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('restock.recommendations'))
        ->assertForbidden();
});
