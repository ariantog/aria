<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\LocationAccessService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->locationA = Location::create(['name' => 'Location A']);
    $this->locationB = Location::create(['name' => 'Location B']);

    $this->addrbookA = Addrbook::factory()->create(['name' => 'Contact A']);
    $this->addrbookB = Addrbook::factory()->create(['name' => 'Contact B']);

    $this->addrbookA->locations()->attach($this->locationA->id);
    $this->addrbookB->locations()->attach($this->locationB->id);

    $this->user = User::factory()->create(['location_id' => $this->locationA->id]);

    Permission::firstOrCreate(['name' => 'addrbook-supplier-list']);
    Permission::firstOrCreate(['name' => 'transactions-list']);
    $this->user->givePermissionTo(['addrbook-supplier-list', 'transactions-list']);
});

it('filters customers by user location', function () {
    $visible = Addrbook::query()->visibleToUser($this->user)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id)
        ->not->toContain($this->addrbookB->id);
});

it('allows superadmin to see all customers', function () {
    $superadmin = User::find(1);

    $visible = Addrbook::query()->visibleToUser($superadmin)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id, $this->addrbookB->id);
});

it('filters transactions by participant location', function () {
    $visibleTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    $ids = Transaction::query()->visibleToUser($this->user)->pluck('id');

    expect($ids)->toContain($visibleTx->id)
        ->not->toContain($hiddenTx->id);
});

it('shows transaction when only receiver is in user location', function () {
    $visibleTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookA->id,
    ]);

    $ids = Transaction::query()->visibleToUser($this->user)->pluck('id');

    expect($ids)->toContain($visibleTx->id);
});

it('filters item transaction details by parent transaction location', function () {
    $item = \App\Models\Item::factory()->create();

    $visibleTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    \App\Models\TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'item_id' => $item->id,
    ]);
    \App\Models\TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'item_id' => $item->id,
    ]);

    $detailIds = \App\Models\TransactionDetail::query()
        ->where('item_id', $item->id)
        ->visibleToUser($this->user)
        ->pluck('transaction_id');

    expect($detailIds)->toContain($visibleTx->id)
        ->not->toContain($hiddenTx->id);
});

it('reports location access through service helper', function () {
    $service = app(LocationAccessService::class);

    expect($service->canAccessAddrbook($this->user, $this->addrbookA))->toBeTrue()
        ->and($service->canAccessAddrbook($this->user, $this->addrbookB))->toBeFalse();
});

it('treats users without location as unrestricted', function () {
    $unrestricted = User::factory()->create(['location_id' => null]);

    $visible = Addrbook::query()->visibleToUser($unrestricted)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id, $this->addrbookB->id);
});

it('treats users with legacy location_id zero as unrestricted', function () {
    $service = app(LocationAccessService::class);
    $legacyUser = User::factory()->make(['location_id' => 0]);

    expect($service->hasUnrestrictedLocationAccess($legacyUser))->toBeTrue();

    $visible = Addrbook::query()->visibleToUser($legacyUser)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id, $this->addrbookB->id);
});

it('shows all transactions when user has no location assignment', function () {
    $unrestricted = User::factory()->create(['location_id' => null]);
    $unrestricted->givePermissionTo('transactions-list');

    $visibleTx = Transaction::factory()->create([
        'invoice' => 'NOLOC-VISIBLE-TX',
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'invoice' => 'NOLOC-OTHER-LOC-TX',
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    $this->actingAs($unrestricted)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('NOLOC-VISIBLE-TX')
        ->assertSee('NOLOC-OTHER-LOC-TX');
});

it('filters the transactions index by location', function () {
    $visibleTx = Transaction::factory()->create([
        'invoice' => 'LOC-VISIBLE-TX',
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'invoice' => 'LOC-HIDDEN-TX',
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.index'));

    $response->assertOk()
        ->assertSee('LOC-VISIBLE-TX')
        ->assertDontSee('LOC-HIDDEN-TX');
});

it('filters addrbook transactions by location', function () {
    Permission::firstOrCreate(['name' => 'addrbook-supplier-list']);
    $this->user->givePermissionTo('addrbook-supplier-list');

    $visibleTx = Transaction::factory()->create([
        'invoice' => 'ADDR-VISIBLE-TX',
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'invoice' => 'ADDR-HIDDEN-TX',
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('addrbook.transactions', $this->addrbookA));

    $response->assertOk()
        ->assertSee('ADDR-VISIBLE-TX')
        ->assertDontSee('ADDR-HIDDEN-TX');
});

it('filters item transactions by location', function () {
    Permission::firstOrCreate(['name' => 'items-list']);
    $this->user->givePermissionTo('items-list');

    $item = Item::factory()->create();

    $visibleTx = Transaction::factory()->create([
        'invoice' => 'ITEM-VISIBLE-TX',
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'invoice' => 'ITEM-HIDDEN-TX',
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    TransactionDetail::factory()->create(['transaction_id' => $visibleTx->id, 'item_id' => $item->id]);
    TransactionDetail::factory()->create(['transaction_id' => $hiddenTx->id, 'item_id' => $item->id]);

    $response = $this->actingAs($this->user)->get(route('items.transactions', $item));

    $response->assertOk()
        ->assertSee('ITEM-VISIBLE-TX')
        ->assertDontSee('ITEM-HIDDEN-TX');
});

it('filters asset lancar transactions by location', function () {
    Permission::firstOrCreate(['name' => 'items-list']);
    $this->user->givePermissionTo('items-list');

    $item = Item::factory()->create(['type' => ItemType::ASSET_LANCAR]);

    $visibleTx = Transaction::factory()->create([
        'invoice' => 'ASSET-VISIBLE-TX',
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'invoice' => 'ASSET-HIDDEN-TX',
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    TransactionDetail::factory()->create(['transaction_id' => $visibleTx->id, 'item_id' => $item->id]);
    TransactionDetail::factory()->create(['transaction_id' => $hiddenTx->id, 'item_id' => $item->id]);

    $response = $this->actingAs($this->user)->get(route('assetlancar.transactions', $item));

    $response->assertOk()
        ->assertSee('ASSET-VISIBLE-TX')
        ->assertDontSee('ASSET-HIDDEN-TX');
});
