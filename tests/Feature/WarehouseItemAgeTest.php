<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\BookClosingService;
use App\Services\PermissionGenerator;
use App\Services\Warehouse\WarehouseItemAgeService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Addrbook');
});

it('requires warehouse-items permission for item age page', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-list');

    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)
        ->get(route('addrbook.type.item-age', ['warehouse', $warehouse->id]))
        ->assertForbidden();
});

it('shows calendar age from last buy or move into the warehouse', function () {
    $this->travelTo('2026-06-15');
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $supplier = Addrbook::factory()->supplier()->create();
    $otherWarehouse = Addrbook::factory()->warehouse()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Utama']);

    $oldInboundItem = Item::factory()->create(['code' => 'AGE-OLD', 'name' => 'Old Stock SKU']);
    $recentItem = Item::factory()->create(['code' => 'AGE-NEW', 'name' => 'Recent Stock SKU']);
    $legacyItem = Item::factory()->create(['code' => 'AGE-LEG', 'name' => 'Legacy Stock SKU']);

    foreach ([$oldInboundItem, $recentItem, $legacyItem] as $sku) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $sku->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 5,
        ]);
    }

    $seedInbound = function (Item $item, string $date, int $type, Addrbook $receiver) use ($supplier, $otherWarehouse): void {
        $sender = $type === Transaction::TYPE_MOVE ? $otherWarehouse : $supplier;
        $txn = Transaction::factory()->create([
            'type' => $type,
            'date' => $date,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        DB::table('transaction_details')->insert([
            'transaction_id' => $txn->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 1000,
            'discount' => 0,
            'total' => 10_000,
            'date' => $date,
            'transaction_type' => $type,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'transaction_disc' => 0,
        ]);
    };

    $minAllowed = app(BookClosingService::class)->getMinAllowedDate()->toDateString();
    expect($minAllowed)->toBe('2026-05-01');

    $seedInbound($oldInboundItem, '2026-01-10', Transaction::TYPE_BUY, $warehouse);
    $seedInbound($oldInboundItem, '2026-06-01', Transaction::TYPE_BUY, $warehouse);
    $seedInbound($recentItem, '2026-06-10', Transaction::TYPE_MOVE, $warehouse);
    $seedInbound($legacyItem, '2024-08-01', Transaction::TYPE_BUY, $warehouse);

    $response = $this->actingAs($user)
        ->get(route('addrbook.type.item-age', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertSee('data-testid="warehouse-item-age-table"', false)
        ->assertSee('Item age', false)
        ->assertSee('Book closing', false);

    $html = $response->getContent();

    expect($html)->toContain('AGE-OLD')
        ->and($html)->toContain('2026-06-01')
        ->and($html)->toContain('AGE-NEW')
        ->and($html)->toContain('2026-06-10')
        ->and($html)->toContain('AGE-LEG')
        ->and($html)->toContain('2024-08-01');

    $paginator = app(WarehouseItemAgeService::class)->paginate($warehouse, request(), $user);
    $meta = app(WarehouseItemAgeService::class)->decorateRows($paginator->getCollection());
    expect($meta[$oldInboundItem->id]['age_days'])->toBe(14)
        ->and($meta[$recentItem->id]['age_days'])->toBe(5)
        ->and($meta[$legacyItem->id]['age_days'])->toBeGreaterThan(600);
});

it('sorts by age descending with unknown inbound first', function () {
    $this->travelTo('2026-06-15');
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $unknown = Item::factory()->create(['code' => 'SORT-UNK', 'name' => 'Unknown inbound']);
    $older = Item::factory()->create(['code' => 'SORT-OLD', 'name' => 'Older inbound']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $unknown->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $older->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);

    $supplier = Addrbook::factory()->supplier()->create();
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'date' => '2026-01-01',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
    ]);
    DB::table('transaction_details')->insert([
        'transaction_id' => $buy->id,
        'item_id' => $older->id,
        'quantity' => 1,
        'price' => 100,
        'discount' => 0,
        'total' => 100,
        'date' => '2026-01-01',
        'transaction_type' => Transaction::TYPE_BUY,
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'transaction_disc' => 0,
    ]);

    $html = $this->actingAs($user)
        ->get(route('addrbook.type.item-age', ['warehouse', $warehouse->id, 'sort' => 'agedesc']))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'SORT-UNK'))->toBeLessThan(strpos($html, 'SORT-OLD'));
});

it('exposes item age tab on warehouse addrbook pages', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertSee('/warehouse/'.$warehouse->id.'/item-age', false)
        ->assertSee('Item age', false);
});
