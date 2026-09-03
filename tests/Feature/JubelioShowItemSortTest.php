<?php

use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\User;
use App\Services\Jubelio\JubelioOrderShowPresenter;

it('sorts jubelio show items by sku by default', function () {
    $user = User::factory()->create();

    mockJubelioSalesOrder('jb-sku-sort', [
        'salesorder_no' => 'INV-SKU-SORT-JB',
        'items' => [
            ['item_code' => 'ZEBRA-99', 'qty' => 1, 'price' => 30000],
            ['item_code' => 'ALPHA-01', 'qty' => 2, 'price' => 10000],
            ['item_code' => 'MID-10', 'qty' => 1, 'price' => 20000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'jb-sku-sort',
        'source' => 1,
        'invoice' => 'INV-SKU-SORT-JB',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $html = $this->actingAs($user)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee('data-testid="jubelio-item-row"', false)
        ->getContent();

    preg_match_all('/data-testid="jubelio-item-row" data-sku="([^"]*)"/', $html, $matches);

    expect($matches[1])->toBe(['ALPHA-01', 'MID-10', 'ZEBRA-99']);
});

it('sorts enriched jubelio payload items by sku', function () {
    Item::factory()->create(['code' => 'ZEBRA-99']);
    Item::factory()->create(['code' => 'ALPHA-01']);

    $items = app(JubelioOrderShowPresenter::class)->enrichItems([
        ['item_code' => 'ZEBRA-99', 'quantity' => 1, 'price' => 30],
        ['item_code' => 'ALPHA-01', 'quantity' => 2, 'price' => 10],
        ['item_code' => 'MID-10', 'quantity' => 1, 'price' => 20],
    ], null);

    expect(array_column($items, 'item_code'))->toBe(['ALPHA-01', 'MID-10', 'ZEBRA-99']);
});
