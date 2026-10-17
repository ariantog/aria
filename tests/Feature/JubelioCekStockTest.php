<?php

use App\Models\Item;
use App\Models\User;
use App\Services\JubelioService;
use Mockery\MockInterface;

it('renders cek stock page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.stock.cek'))
        ->assertSuccessful()
        ->assertSee('Jubelio Cek Stok')
        ->assertSee('Jubelio item_id');
});

it('shows raw all-stocks json for a jubelio item id', function () {
    $user = User::factory()->create();

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->with([901])
            ->andReturn([
                'data' => [[
                    'item_id' => 901,
                    'total_stocks' => [
                        'on_hand' => 25,
                        'available' => 23,
                        'reserved' => 2,
                        'on_order' => 0,
                    ],
                    'location_stocks' => [[
                        'location_id' => 5,
                        'on_hand' => 25,
                        'reserved' => 2,
                        'available' => 25,
                    ]],
                ]],
            ]);
    });

    $this->actingAs($user)
        ->get(route('jubelio.stock.cek', ['item_id' => '901']))
        ->assertSuccessful()
        ->assertSee('data-testid="jubelio-cek-stock-json"', false)
        ->assertSee('901', false)
        ->assertSee('item_id', false)
        ->assertSee('parity', false);
});

it('links aria sku when jubelio item id is mapped', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create([
        'jubelio_item_id' => 902,
        'code' => 'CEK-STOCK-LINK',
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->andReturn([
                'data' => [[
                    'item_id' => 902,
                    'location_stocks' => [],
                    'total_stocks' => ['on_hand' => 1, 'available' => 1, 'reserved' => 0, 'on_order' => 0],
                ]],
            ]);
    });

    $this->actingAs($user)
        ->get(route('jubelio.stock.cek', ['item_id' => '902']))
        ->assertSuccessful()
        ->assertSee('CEK-STOCK-LINK', false)
        ->assertSee(route('items.show', $item), false);
});
