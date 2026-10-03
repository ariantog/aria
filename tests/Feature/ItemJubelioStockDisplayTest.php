<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    Role::create(['name' => 'Super Admin']);
    $this->user->assignRole('Super Admin');
});

function mockJubelioAllStocksForItem(int $jubelioItemId, array $totalStocks, array $locationStocks = []): void
{
    test()->mock(JubelioService::class, function (MockInterface $mock) use ($jubelioItemId, $totalStocks, $locationStocks) {
        $mock->shouldReceive('fetchItemStocks')
            ->once()
            ->with([$jubelioItemId])
            ->andReturn([
                $jubelioItemId => [
                    'item_id' => $jubelioItemId,
                    'total_stocks' => $totalStocks,
                    'location_stocks' => $locationStocks,
                ],
            ]);
    });
}

it('shows jubelio totals on manufactured item detail', function () {
    $item = Item::factory()->create([
        'jubelio_item_id' => 501,
        'code' => 'JUB-DETAIL-M',
    ]);

    mockJubelioAllStocksForItem(501, [
        'on_hand' => 12,
        'on_order' => 3,
        'reserved' => 2,
        'available' => 10,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Jubelio Inventory', false)
        ->assertSee('data-testid="jubelio-total-on-hand"', false)
        ->assertSee(format_amount(12, 0), false)
        ->assertSee(format_amount(3, 0), false)
        ->assertSee(format_amount(2, 0), false)
        ->assertSee(format_amount(10, 0), false);
});

it('shows jubelio totals on asset lancar detail', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'jubelio_item_id' => 502,
        'code' => 'JUB-ASSET-DET',
    ]);

    mockJubelioAllStocksForItem(502, [
        'on_hand' => 7,
        'on_order' => 0,
        'reserved' => 1,
        'available' => 6,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('data-testid="jubelio-total-reserved"', false)
        ->assertSee(format_amount(6, 0), false);
});

it('shows per-warehouse jubelio columns when gudang is mapped', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Mapped WH']);
    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Loc 10',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    $item = Item::factory()->create([
        'jubelio_item_id' => 503,
        'code' => 'JUB-WH-DET',
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 15,
    ]);

    mockJubelioAllStocksForItem(503, [
        'on_hand' => 20,
        'on_order' => 0,
        'reserved' => 0,
        'available' => 20,
    ], [[
        'location_id' => 10,
        'on_hand' => 15,
        'on_order' => 1,
        'reserved' => 2,
        'available' => 13,
    ]]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('JB on hand', false)
        ->assertSee(format_amount(13, 0), false)
        ->assertSee('bg-amber-50', false);
});
