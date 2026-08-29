<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use Spatie\Permission\Models\Permission;

function seedJubelioSyncRow(Addrbook $warehouse, int $locationId = 10, string $locationName = 'Gudang Pusat'): Jubeliosync
{
    return Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => $locationId,
        'jubelio_location_name' => $locationName,
    ]);
}

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'transactions-type-buy']);

    User::factory()->create();
    $this->user = User::factory()->create();
});

it('exposes jubelio_item_id from the database in item lookup payloads', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $linked = Item::factory()->create(['jubelio_item_id' => 456]);
    $unlinked = Item::factory()->create(['jubelio_item_id' => null]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $linked->id]))
        ->assertSuccessful()
        ->assertJsonPath('item.jubelio_item_id', 456);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $unlinked->id]))
        ->assertSuccessful()
        ->assertJsonPath('item.jubelio_item_id', 0);
});

it('builds create-form jubelio sync config from jubeliosyncs table only', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $otherWarehouse = Addrbook::factory()->warehouse()->create();

    seedJubelioSyncRow($warehouse);
    seedJubelioSyncRow($otherWarehouse, locationId: 0, locationName: 'Unmapped');

    $presenter = app(JubelioTransactionSyncPresenter::class);

    expect($presenter->createFormSyncConfig('sell'))->toMatchArray([
        'sync_sender' => true,
        'sync_receiver' => false,
        'synced_warehouse_ids' => [$warehouse->id],
    ])->and($presenter->createFormSyncConfig('buy'))->toMatchArray([
        'sync_sender' => false,
        'sync_receiver' => true,
        'synced_warehouse_ids' => [$warehouse->id],
    ]);
});

it('embeds db-backed jubelio sync config and warning hooks on the sell create form', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $warehouse = Addrbook::factory()->warehouse()->create();
    seedJubelioSyncRow($warehouse);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('data-testid="jubelio-unlinked-warning"', false)
        ->assertSee('"sync_sender":true', false)
        ->assertSee('"synced_warehouse_ids":['.$warehouse->id.']', false)
        ->assertSee('hasJubelioUnlinkedWarning()', false);
});
