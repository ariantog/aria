<?php

use App\Models\Addrbook;
use App\Models\DeletedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
});

it('blocks deleting a sole jubelio-synced transaction', function () {
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
        ->from(route('transactions.show', $transaction))
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.show', $transaction))
        ->assertSessionHas('error', 'Jubelio-synced transactions cannot be deleted.');

    expect(Transaction::find($transaction->id))->not->toBeNull();
});

it('allows deleting a duplicate jubelio-synced transaction when another shares the invoice', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $keeper = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-260825AEKPSTXG',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => '2026-08-25',
    ]);

    $duplicate = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-260825AEKPSTXG',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => '2026-08-26',
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $duplicate))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect(Transaction::find($duplicate->id))->toBeNull()
        ->and(Transaction::find($keeper->id))->not->toBeNull()
        ->and(DeletedTransaction::find($duplicate->id))->not->toBeNull();
});

it('still blocks deleting the last remaining jubelio duplicate after one is removed', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $first = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DUP-PAIR',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $second = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DUP-PAIR',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)->delete(route('transactions.destroy', $second))->assertSessionHas('success');

    $this->actingAs($user)
        ->from(route('transactions.show', $first))
        ->delete(route('transactions.destroy', $first))
        ->assertRedirect(route('transactions.show', $first))
        ->assertSessionHas('error', 'Jubelio-synced transactions cannot be deleted.');

    expect(Transaction::find($first->id))->not->toBeNull();
});

it('hides delete action on transaction show for non-duplicate jubelio sync', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-SOLO',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->assertDontSee('data-testid="delete-transaction-button"', false);
});

it('shows delete action on transaction show for duplicate jubelio sync', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DUP-SHOW',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $duplicate = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DUP-SHOW',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $duplicate))
        ->assertSuccessful()
        ->assertSee('data-testid="delete-transaction-button"', false);
});
