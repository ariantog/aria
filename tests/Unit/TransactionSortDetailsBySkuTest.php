<?php

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sorts loaded details by item sku and leaves empty details alone', function () {
    $transaction = Transaction::factory()->create();
    $late = Item::factory()->create(['code' => 'ZEBRA-99']);
    $early = Item::factory()->create(['code' => 'ALPHA-01']);
    $mid = Item::factory()->create(['code' => 'MID-10']);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $late->id,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $early->id,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $mid->id,
    ]);

    $empty = Transaction::factory()->create();
    expect($empty->sortDetailsBySku()->relationLoaded('details'))->toBeFalse();

    $transaction->load('details.item');

    expect($transaction->details->pluck('item.code')->all())
        ->toBe(['ZEBRA-99', 'ALPHA-01', 'MID-10']);

    $transaction->sortDetailsBySku();

    expect($transaction->details->pluck('item.code')->all())
        ->toBe(['ALPHA-01', 'MID-10', 'ZEBRA-99']);
});
