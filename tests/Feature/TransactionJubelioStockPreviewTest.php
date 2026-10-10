<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\JubelioService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);

    config(['services.jubelio.active' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('transactions-type-sell');
});

it('returns jubelio location quantities for linked items at a mapped warehouse', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Online',
    ]);

    $linked = Item::factory()->create(['jubelio_item_id' => 501]);
    $unlinked = Item::factory()->create(['jubelio_item_id' => null]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->with([501])
            ->andReturn([
                'data' => [[
                    'item_id' => 501,
                    'location_stocks' => [[
                        'location_id' => 10,
                        'on_hand' => 3,
                        'on_order' => 2,
                        'reserved' => 0,
                        'available' => 1,
                    ]],
                ]],
            ]);
    });

    $this->actingAs($this->user)
        ->postJson(route('transactions.jubelio-stock-preview', ['type' => 'sell']), [
            'warehouse_id' => $warehouse->id,
            'item_ids' => [$linked->id, $unlinked->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('fetch_failed', false)
        ->assertJsonPath("stocks.{$linked->id}.available", 1)
        ->assertJsonPath("stocks.{$linked->id}.on_order", 2)
        ->assertJsonPath("stocks.{$unlinked->id}.linked", false);
});

it('returns empty payload when jubelio integration is inactive', function () {
    config(['services.jubelio.active' => false]);

    $this->actingAs($this->user)
        ->postJson(route('transactions.jubelio-stock-preview', ['type' => 'sell']), [
            'warehouse_id' => 1,
            'item_ids' => [1],
        ])
        ->assertSuccessful()
        ->assertJsonPath('inactive', true)
        ->assertJsonPath('stocks', []);
});
