<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\DeletedTransaction;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

function grantDeleteAccess(): User
{
    Gate::before(fn () => true);

    return User::factory()->create();
}

it('allows deleting a jubelio-synced transaction', function () {
    $user = grantDeleteAccess();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-260825AEKPSTXG',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect(Transaction::find($transaction->id))->toBeNull()
        ->and(DeletedTransaction::find($transaction->id))->not->toBeNull();
});

it('reverts stock and balance when deleting a posted jubelio sell', function () {
    $user = grantDeleteAccess();
    $this->actingAs($user);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['qty' => 10, 'price' => 50_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 10,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-JUBELIO-STOCK',
        'date' => now()->toDateString(),
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'total' => -50_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 50_000,
        'total' => 50_000,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))->toBe(9.0)
        ->and((float) $item->fresh()->qty)->toBe(9.0)
        ->and((float) (AddrbookStat::where('customer_id', $customer->id)->value('balance') ?? 0))->toBe(-50_000.0);

    $this->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect(Transaction::find($transaction->id))->toBeNull()
        ->and(DeletedTransaction::find($transaction->id))->not->toBeNull()
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))->toBe(10.0)
        ->and((float) $item->fresh()->qty)->toBe(10.0)
        ->and((float) (AddrbookStat::where('customer_id', $customer->id)->value('balance') ?? 0))->toBe(0.0);
});

it('still rejects deleting a jubelio-synced transaction inside a closed book period', function () {
    Carbon::setTestNow('2026-03-15');

    $user = grantDeleteAccess();
    $this->actingAs($user);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-JUBELIO-CLOSED',
        'date' => '2026-01-15',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'total' => -1000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $user->id,
    ]);

    $this->delete(route('transactions.destroy', $transaction))
        ->assertSessionHasErrors('date');

    expect(Transaction::find($transaction->id))->not->toBeNull();

    Carbon::setTestNow();
});

it('requires transactions-delete permission', function () {
    Permission::firstOrCreate(['name' => 'transactions-delete', 'guard_name' => 'web']);

    User::factory()->create();
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-JUBELIO-AUTH',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();

    expect(Transaction::find($transaction->id))->not->toBeNull();
});

it('shows delete action on transaction show for jubelio sync', function () {
    $user = grantDeleteAccess();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-JUBELIO-DELETE',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->assertSee('data-testid="delete-transaction-button"', false);
});
