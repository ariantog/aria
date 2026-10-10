<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    foreach ([
        'transactions-type-sell',
        'transactions-type-move',
        'transactions-submit-jubelio-unlinked',
    ] as $name) {
        Permission::firstOrCreate(['name' => $name]);
    }

    $this->user = User::factory()->create();
    expect($this->user->id)->not->toBe(User::SUPERADMIN_ID);
    $this->actingAs($this->user);
});

function jubelioGateSync(Addrbook $warehouse): Jubeliosync
{
    return Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Online',
    ]);
}

function jubelioGateSellPayload(Addrbook $warehouse, Addrbook $customer, Item $item, float $qty = 1): array
{
    return [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => $qty,
                'price' => 10_000,
                'discount' => 0,
            ],
        ],
    ];
}

it('blocks sell store when sender is jubelio-mapped and a line is unlinked', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $warehouse = Addrbook::factory()->warehouse()->create();
    jubelioGateSync($warehouse);
    $customer = Addrbook::factory()->customer()->create();
    $linked = Item::factory()->create(['jubelio_item_id' => 1001]);
    $unlinked = Item::factory()->create(['jubelio_item_id' => null]);

    foreach ([$linked, $unlinked] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::class,
            'quantity' => 20,
        ]);
    }

    $this->postJson(route('transactions.store'), array_merge(
        jubelioGateSellPayload($warehouse, $customer, $linked),
        [
            'items' => [
                ['item_id' => $linked->id, 'quantity' => 1, 'price' => 10_000, 'discount' => 0],
                ['item_id' => $unlinked->id, 'quantity' => 1, 'price' => 10_000, 'discount' => 0],
            ],
        ],
    ))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);

    expect(Transaction::query()->where('type', Transaction::TYPE_SELL)->count())->toBe(0);
});

it('allows sell store with unlinked lines when submit-jubelio-unlinked permission is granted', function () {
    $this->user->givePermissionTo(['transactions-type-sell', 'transactions-submit-jubelio-unlinked']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    jubelioGateSync($warehouse);
    $customer = Addrbook::factory()->customer()->create();
    $unlinked = Item::factory()->create(['jubelio_item_id' => null]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $unlinked->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 5,
    ]);

    $this->postJson(route('transactions.store'), jubelioGateSellPayload($warehouse, $customer, $unlinked))
        ->assertCreated();

    expect(Transaction::query()->where('type', Transaction::TYPE_SELL)->count())->toBe(1);
});

it('blocks move store when receiver is jubelio-mapped and a line is unlinked', function () {
    $this->user->givePermissionTo('transactions-type-move');

    $sender = Addrbook::factory()->warehouse()->create();
    $receiver = Addrbook::factory()->warehouse()->create();
    jubelioGateSync($receiver);
    $item = Item::factory()->create(['jubelio_item_id' => null]);

    WarehouseItem::create([
        'warehouse_id' => $sender->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 5,
    ]);

    $this->postJson(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'move',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'price' => 1000, 'discount' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

it('does not block sell when warehouse is not jubelio-mapped even if sku is unlinked', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $unlinked = Item::factory()->create(['jubelio_item_id' => null]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $unlinked->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 5,
    ]);

    $this->postJson(route('transactions.store'), jubelioGateSellPayload($warehouse, $customer, $unlinked))
        ->assertCreated();
});

it('embeds jubelio unlinked submit gate on sell and move create forms', function () {
    $this->user->givePermissionTo(['transactions-type-sell', 'transactions-type-move']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    jubelioGateSync($warehouse);

    $this->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('data-testid="jubelio-unlinked-submit-block"', false)
        ->assertSee('jubelioUnlinkedSubmitBlocked()', false)
        ->assertSee('hasJubelioUnlinkedLinesForSubmit()', false)
        ->assertSee('_CanSubmitJubelioUnlinked', false);

    $this->get(route('transactions.create', ['type' => 'move']))
        ->assertOk()
        ->assertSee('jubelioUnlinkedSubmitBlocked()', false);
});

it('shows can submit jubelio unlinked flag when user has permission', function () {
    $this->user->givePermissionTo(['transactions-type-sell', 'transactions-submit-jubelio-unlinked']);

    $this->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('const _CanSubmitJubelioUnlinked = true', false);
});
