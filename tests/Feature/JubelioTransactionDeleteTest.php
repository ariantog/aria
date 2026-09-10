<?php

use App\Models\Addrbook;
use App\Models\DeletedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
});

it('allows deleting a jubelio-synced transaction', function () {
    $user = User::factory()->create();
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

it('shows delete action on transaction show for jubelio sync', function () {
    $user = User::factory()->create();
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
