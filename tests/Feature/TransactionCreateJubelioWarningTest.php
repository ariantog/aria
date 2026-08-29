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
    Permission::firstOrCreate(['name' => 'transactions-type-move']);

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

it('lists every jubeliosyncs warehouse_id for create-form warnings regardless of location id', function () {
    $mapped = Addrbook::factory()->warehouse()->create();
    $locationZero = Addrbook::factory()->warehouse()->create();
    $unsynced = Addrbook::factory()->warehouse()->create();

    seedJubelioSyncRow($mapped);
    seedJubelioSyncRow($locationZero, locationId: 0, locationName: 'Pending location');

    $ids = app(JubelioTransactionSyncPresenter::class)->createFormSyncConfig()['synced_warehouse_ids'];

    expect($ids)->toContain($mapped->id, $locationZero->id)
        ->not->toContain($unsynced->id);
});

it('embeds db-backed jubelio sync config and warning hooks on the sell create form', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $warehouse = Addrbook::factory()->warehouse()->create();
    seedJubelioSyncRow($warehouse);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('data-testid="jubelio-unlinked-warning"', false)
        ->assertSee('"synced_warehouse_ids":['.$warehouse->id.']', false)
        ->assertSee('jubelioWarehouseMapped()', false)
        ->assertSee("_TxType === 'sell'", false);
});

it('embeds move-specific sender-or-receiver jubelio check on the move create form', function () {
    $this->user->givePermissionTo('transactions-type-move');

    $syncedReceiver = Addrbook::factory()->warehouse()->create(['name' => 'Synced Dest']);
    seedJubelioSyncRow($syncedReceiver);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'move']))
        ->assertOk()
        ->assertSee("_TxType === 'move'", false)
        ->assertSee('mapped(this.form.sender_id) || mapped(this.form.receiver_id)', false)
        ->assertSee('"synced_warehouse_ids":['.$syncedReceiver->id.']', false);
});
