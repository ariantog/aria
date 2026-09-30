<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-move']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('transactions-type-move');
});

it('embeds move duplicate row merge helpers on the move create form', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'move']))
        ->assertOk()
        ->assertSee('mergeMoveDuplicateRowAt(idx)', false)
        ->assertSee('consolidateMoveDuplicateRows()', false)
        ->assertSee('moveRowPricesMatch(a, b)', false)
        ->assertSee("if (_TxType === 'move')", false);
});
