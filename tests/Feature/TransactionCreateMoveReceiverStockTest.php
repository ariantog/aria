<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-move']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('transactions-type-move');
});

it('shows from and to stock columns and refreshes receiver qty on move create form', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'move']))
        ->assertOk()
        ->assertSee('From Stock', false)
        ->assertSee('To Stock', false)
        ->assertSee('data-testid="move-from-stock"', false)
        ->assertSee('data-testid="move-to-stock"', false)
        ->assertSee('receiver_warehouse_stock', false)
        ->assertSee('receiverStockFor(row)', false)
        ->assertSee("if (_TxType === 'move') {", false)
        ->assertSee('row.receiver_warehouse_stock = this.receiverStockFor(row);', false);
});

it('does not show dual stock columns on sell create form', function () {
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    $this->user->givePermissionTo('transactions-type-sell');

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('Whs. Stock', false)
        ->assertDontSee('data-testid="move-to-stock"', false);
});
