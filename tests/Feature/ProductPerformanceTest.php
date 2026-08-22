<?php

use App\Actions\Transactions\Concerns\CalculatesTransactionTotals;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\ProductPerformanceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates item totals using row discount as a percentage', function () {
    $calc = new class
    {
        use CalculatesTransactionTotals;

        public function total(float $qty, float $price, float $discountPercent): float
        {
            return $this->calculateItemTotal($qty, $price, $discountPercent);
        }
    };

    expect($calc->total(10, 1000, 10))->toBe(9000.0);
    expect($calc->total(2, 50000, 0))->toBe(100000.0);
});

it('stores row discount percent on sell transactions', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['price' => 10000, 'cost' => 5000]);

    \App\Models\WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 20,
    ]);

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 10000,
            'discount' => 10,
        ]],
        'discount' => 0,
    ]);

    $transaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $transaction));

    $detail = $transaction->details()->first();
    expect((float) $detail->discount)->toBe(10.0);
    expect((float) $detail->total)->toBe(18000.0);
});

it('builds product performance rollups from warehouse monthly stats', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $group = ItemGroup::factory()->create(['master' => 'GLOVE', 'name' => 'Glove']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
    $typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'GLOVE', 'name' => 'Glove']);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'genre' => $typeTag->id,
        'size' => $sizeTag->id,
        'pcode' => 'CX12345/01',
        'name' => 'Glove M',
    ]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 10,
        'returned_qty' => 2,
        'sold_value' => 100000,
        'returned_value' => 10000,
        'item_type' => ItemType::ITEM->value,
        'group_id' => $group->id,
        'pcode' => 'CX12345/01',
        'type_code' => 'GLOVE',
        'warna_code' => '-',
        'size_code' => 'M',
    ]);

    $result = app(ProductPerformanceSyncService::class)->syncAll();
    expect($result['rollups'])->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create())
        ->get(route('reports.product-performance', ['tab' => 'sales', 'period' => 90]))
        ->assertOk()
        ->assertSee('Product Performance', false)
        ->assertSee('Glove M', false);
});

it('redirects legacy contributors url to product performance', function () {
    $this->actingAs(User::factory()->create())
        ->get('/contributors')
        ->assertRedirect('/reports/product-performance');
});

it('normalizes legacy group_id zero when resolving item dimensions', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'KNEESUPPORT-03',
        'code' => 'KNEESUPPORT-03-L',
    ]);
    $item->group_id = 0;

    $dims = app(\App\Services\Items\ItemDimensionResolver::class)->resolve($item);

    expect($dims['group_id'])->toBeNull();
});

it('renders item stats from cached warehouse monthly stats with period presets', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['name' => 'Stats SKU', 'code' => 'STAT-01']);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 5,
        'returned_qty' => 1,
        'sold_value' => 50000,
        'returned_value' => 5000,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('items.stats', ['item' => $item, 'period' => 90]))
        ->assertOk()
        ->assertSee('Item Statistics', false)
        ->assertSee('Sold qty', false)
        ->assertSee(now()->format('F Y'), false);
});
