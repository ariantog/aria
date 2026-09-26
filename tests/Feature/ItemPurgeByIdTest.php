<?php

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->superadmin = User::query()->find(1) ?? User::factory()->create(['id' => 1]);
    $this->otherUser = User::factory()->create();
    expect($this->otherUser->id)->not->toBe(User::SUPERADMIN_ID);
});

it('previews an item without transaction details as deletable', function () {
    $item = Item::factory()->create(['code' => 'ORPHAN-SKU', 'name' => 'Orphan Product']);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.item-purge.index', ['item_id' => $item->id]))
        ->assertSuccessful()
        ->assertSee('ORPHAN-SKU')
        ->assertSee('No transaction details — eligible for deletion')
        ->assertSee('data-testid="item-purge-deletable"', false);
});

it('previews an item with transaction details as not deletable', function () {
    $item = Item::factory()->create(['code' => 'BUSY-SKU']);
    $transaction = Transaction::factory()->create(['date' => '2020-01-10']);
    DB::table('transaction_details')->insert([
        'id' => 92001,
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100,
        'discount' => 0,
        'total' => 100,
        'date' => '2020-01-10',
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 1,
        'receiver_id' => 1,
        'transaction_disc' => 0,
    ]);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.item-purge.index', ['item_id' => $item->id]))
        ->assertSuccessful()
        ->assertSee('BUSY-SKU')
        ->assertSee('Present in transaction details — cannot delete')
        ->assertDontSee('data-testid="item-purge-form"', false);
});

it('hard deletes an item that never appeared in transaction details', function () {
    $item = Item::factory()->create(['code' => 'DELETE-ME']);

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.item-purge.destroy'), [
            'item_id' => $item->id,
            'confirm' => 'DELETE-ITEM',
        ])
        ->assertRedirect(route('data-retention.item-purge.index'))
        ->assertSessionHas('success');

    expect(DB::table('items')->where('id', $item->id)->exists())->toBeFalse();
});

it('rejects deleting an item that appears in transaction details', function () {
    $item = Item::factory()->create();
    $transaction = Transaction::factory()->create(['date' => '2020-01-10']);
    DB::table('transaction_details')->insert([
        'id' => 92002,
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100,
        'discount' => 0,
        'total' => 100,
        'date' => '2020-01-10',
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 1,
        'receiver_id' => 1,
        'transaction_disc' => 0,
    ]);

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.item-purge.destroy'), [
            'item_id' => $item->id,
            'confirm' => 'DELETE-ITEM',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DB::table('items')->where('id', $item->id)->exists())->toBeTrue();
});

it('denies delete by item id for non-superadmin users', function () {
    $item = Item::factory()->create();

    $this->actingAs($this->otherUser)
        ->post(route('data-retention.item-purge.destroy'), [
            'item_id' => $item->id,
            'confirm' => 'DELETE-ITEM',
        ])
        ->assertForbidden();
});

it('hard deletes warehouse stock and monthly stats rows for the item', function () {
    $item = Item::factory()->create();
    $warehouseId = \App\Models\Addrbook::factory()->warehouse()->create()->id;

    DB::table('warehouse_item')->insert([
        'item_id' => $item->id,
        'warehouse_id' => $warehouseId,
        'warehouse_type' => '2',
        'quantity' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (Schema::hasTable('warehouse_item_monthly_stats')) {
        DB::table('warehouse_item_monthly_stats')->insert([
            'warehouse_id' => $warehouseId,
            'item_id' => $item->id,
            'year' => 2026,
            'month' => 3,
            'sold_qty' => 0,
            'returned_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    app(DataRetentionService::class)->deleteItemFromLive($item->id);

    expect(DB::table('items')->where('id', $item->id)->exists())->toBeFalse()
        ->and(DB::table('warehouse_item')->where('item_id', $item->id)->exists())->toBeFalse();

    if (Schema::hasTable('warehouse_item_monthly_stats')) {
        expect(DB::table('warehouse_item_monthly_stats')->where('item_id', $item->id)->exists())->toBeFalse();
    }
});

it('checks transaction_details and deleted_details for eligibility', function () {
    $item = Item::factory()->create();
    $retention = app(DataRetentionService::class);

    expect($retention->itemAppearsInTransactionDetails($item->id))->toBeFalse();

    if (Schema::hasTable('deleted_details')) {
        DB::table('deleted_details')->insert([
            'id' => 93001,
            'transaction_id' => 1,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 50,
            'discount' => 0,
            'total' => 50,
            'date' => '2020-01-01',
            'transaction_type' => Transaction::TYPE_SELL,
            'sender_id' => 1,
            'receiver_id' => 1,
        ]);

        expect($retention->itemAppearsInTransactionDetails($item->id))->toBeTrue();
    }
});
