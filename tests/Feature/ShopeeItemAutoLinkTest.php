<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ShopeeItemLinkAttempt;
use App\Models\Shopeesync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeItemAutoLinkService;
use App\Services\Shopee\ShopeeStockApiService;
use Mockery\MockInterface;

beforeEach(function () {
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_03_100000_install_shopee_stock_tables.php']);
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_05_100000_install_shopee_item_link_auto_tables.php']);

    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    User::factory()->create();
});

function seedShopeeAutoLinkStock(Item $item, Addrbook $warehouse, float $qty = 5): void
{
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => $qty,
    ]);
}

function seedShopeeMappedWarehouse(): Addrbook
{
    $warehouse = Addrbook::factory()->warehouse()->create();

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 1,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 99,
        'shopee_warehouse_name' => 'Pickup',
    ]);

    return $warehouse;
}

it('auto-links when model_sku matches exactly once', function () {
    $warehouse = seedShopeeMappedWarehouse();

    $item = Item::factory()->create([
        'code' => 'AJD-CX90324-05-S',
        'shopee_item_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    seedShopeeAutoLinkStock($item, $warehouse);

    $this->mock(ShopeeStockApiService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isReady')->andReturn(true);
        $mock->shouldReceive('searchItems')
            ->once()
            ->with('AJD-CX90324-05-S', 50)
            ->andReturn([
                ['item_id' => 5001, 'item_sku' => 'CX90324-05', 'item_name' => 'Shirt'],
            ]);
        $mock->shouldReceive('modelsForItem')
            ->once()
            ->with(5001)
            ->andReturn([
                ['model_id' => 9001, 'model_sku' => 'AJD-CX90324-05-S'],
                ['model_id' => 9002, 'model_sku' => 'AJD-CX90324-05-M'],
            ]);
    });

    $result = app(ShopeeItemAutoLinkService::class)->discoverForItem($item->fresh());

    expect($result['outcome'])->toBe(ShopeeItemLinkAttempt::OUTCOME_LINKED)
        ->and($item->fresh()->shopee_item_id)->toBe(5001)
        ->and($item->fresh()->shopee_model_id)->toBe(9001);
});

it('marks ambiguous when two models share the same sku', function () {
    $warehouse = seedShopeeMappedWarehouse();

    $item = Item::factory()->create([
        'code' => 'DUPE-SKU-01',
        'shopee_item_id' => null,
    ]);

    seedShopeeAutoLinkStock($item, $warehouse);

    $this->mock(ShopeeStockApiService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isReady')->andReturn(true);
        $mock->shouldReceive('searchItems')->andReturn([
            ['item_id' => 6001, 'item_sku' => 'DUPE-SKU-01'],
        ]);
        $mock->shouldReceive('modelsForItem')->andReturn([
            ['model_id' => 1, 'model_sku' => 'DUPE-SKU-01'],
            ['model_id' => 2, 'model_sku' => 'DUPE-SKU-01'],
        ]);
    });

    $result = app(ShopeeItemAutoLinkService::class)->discoverForItem($item->fresh());

    expect($result['outcome'])->toBe(ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS)
        ->and($item->fresh()->shopee_item_id)->toBeNull();
});

it('renders shopee auto link dashboard', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->get(route('shopee.auto-link.index'))
        ->assertSuccessful()
        ->assertSee('Shopee Auto Link', false)
        ->assertSee('data-testid="shopee-auto-link-dashboard"', false);
});
