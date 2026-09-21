<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\JubelioItemLinkAttempt;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioItemAutoLinkService;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('jubelio-view', 'web');
    User::factory()->create();
});

function seedAutoLinkStock(Item $item, Addrbook $warehouse, float $qty = 5): void
{
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => $qty,
    ]);
}

function seedJubelioMappedWarehouse(): Addrbook
{
    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    return $warehouse;
}

it('auto-links when jubelio returns one exact item_code match', function () {
    $warehouse = seedJubelioMappedWarehouse();

    $item = Item::factory()->create([
        'code' => 'AUTO-LINK-SKU-01',
        'jubelio_item_id' => null,
        'created_at' => now()->subDays(3),
    ]);

    seedAutoLinkStock($item, $warehouse);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('get')
            ->once()
            ->with(JubelioItemAutoLinkService::TO_STOCK_URL, ['q' => 'AUTO-LINK-SKU-01'])
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                    'data' => [
                        ['item_id' => 88001, 'item_code' => 'AUTO-LINK-SKU-01', 'item_name' => 'Test'],
                        ['item_id' => 88002, 'item_code' => 'OTHER', 'item_name' => 'Other'],
                    ],
                ])),
            ));
    });

    $service = app(JubelioItemAutoLinkService::class);
    $result = $service->discoverForItem($item->fresh());

    expect($result['outcome'])->toBe(JubelioItemLinkAttempt::OUTCOME_LINKED)
        ->and($item->fresh()->jubelio_item_id)->toBe(88001);
});

it('tries legacy_code first for items newer than 30 days', function () {
    $warehouse = seedJubelioMappedWarehouse();

    $item = Item::factory()->create([
        'code' => 'NEW-CODE-01',
        'legacy_code' => 'OLD-LEGACY-01',
        'jubelio_item_id' => null,
        'created_at' => now()->subDays(5),
    ]);

    seedAutoLinkStock($item, $warehouse);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('get')
            ->once()
            ->with(JubelioItemAutoLinkService::TO_STOCK_URL, ['q' => 'OLD-LEGACY-01'])
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                    'data' => [
                        ['item_id' => 88010, 'item_code' => 'OLD-LEGACY-01', 'item_name' => 'Legacy'],
                    ],
                ])),
            ));
    });

    app(JubelioItemAutoLinkService::class)->discoverForItem($item->fresh());

    expect($item->fresh()->jubelio_item_id)->toBe(88010);
});

it('records ambiguous when multiple exact matches exist', function () {
    $warehouse = seedJubelioMappedWarehouse();

    $item = Item::factory()->create([
        'code' => 'DUP-SKU',
        'jubelio_item_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    seedAutoLinkStock($item, $warehouse);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('get')->once()->andReturn(new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'data' => [
                    ['item_id' => 1, 'item_code' => 'DUP-SKU'],
                    ['item_id' => 2, 'item_code' => 'DUP-SKU'],
                ],
            ])),
        ));
    });

    $result = app(JubelioItemAutoLinkService::class)->discoverForItem($item->fresh());

    expect($result['outcome'])->toBe(JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS)
        ->and($item->fresh()->jubelio_item_id)->toBeNull();
});

it('renders auto link dashboard for jubelio viewers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $this->actingAs($user)
        ->get(route('jubelio.auto-link.index'))
        ->assertSuccessful()
        ->assertSee('Jubelio Auto Link');
});

it('filters auto failed items on item links sku view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $failed = Item::factory()->create([
        'code' => 'FAIL-AUTO-LINK',
        'jubelio_item_id' => null,
    ]);

    for ($i = 0; $i < 5; $i++) {
        JubelioItemLinkAttempt::query()->create([
            'item_id' => $failed->id,
            'outcome' => JubelioItemLinkAttempt::OUTCOME_NO_MATCH,
            'search_q' => 'FAIL-AUTO-LINK',
            'candidates_count' => 0,
        ]);
    }

    $ok = Item::factory()->create([
        'code' => 'OK-AUTO-LINK',
        'jubelio_item_id' => null,
    ]);

    JubelioItemLinkAttempt::query()->create([
        'item_id' => $ok->id,
        'outcome' => JubelioItemLinkAttempt::OUTCOME_NO_MATCH,
        'search_q' => 'OK-AUTO-LINK',
        'candidates_count' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.item-links.index', ['view' => 'items', 'link' => 'auto_failed', 'q' => 'AUTO-LINK']))
        ->assertSuccessful()
        ->assertSee('FAIL-AUTO-LINK', false)
        ->assertDontSee('OK-AUTO-LINK', false);
});
