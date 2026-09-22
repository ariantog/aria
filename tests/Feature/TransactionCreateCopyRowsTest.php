<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'transactions-type-move']);
    Permission::firstOrCreate(['name' => 'transactions-type-buy']);

    $this->user = User::factory()->create();
});

it('renders copy rows control on sell and move create forms', function (string $type) {
    $this->user->givePermissionTo('transactions-type-'.$type);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => $type]))
        ->assertOk()
        ->assertSee('data-testid="copy-line-items-table"', false)
        ->assertSee('copyRowsTable()', false)
        ->assertSee('x-ref="lineItemsTable"', false);
})->with(['sell', 'move']);

it('does not render copy rows control on buy create form', function () {
    $this->user->givePermissionTo('transactions-type-buy');

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'buy']))
        ->assertOk()
        ->assertDontSee('data-testid="copy-line-items-table"', false);
});
