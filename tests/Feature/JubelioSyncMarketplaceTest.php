<?php

use App\Models\Addrbook;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\User;
use App\Services\Jubelio\JubelioSyncCatalogService;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config([
        'services.jubelio.active' => true,
        'services.jubelio.verify_ssl' => false,
    ]);

    Permission::firstOrCreate(['name' => 'jubelio-sync']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('jubelio-sync');

    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'test-token',
            'expires_at' => now()->addHour()->toDateTimeString(),
        ],
    ]);
});

it('lists unmapped jubelio stores on discover page', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 101,
        'jubelio_store_name' => 'Shopee Lama',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Http::fake([
        'https://api2.jubelio.com/locations/-1' => Http::response([
            'stores' => [
                ['store_id' => 101, 'store_name' => 'Shopee Lama'],
                ['store_id' => 202, 'store_name' => 'TikTok Shop Baru'],
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->get(route('jubelio.sync.discover'))
        ->assertSuccessful()
        ->assertSee('TikTok Shop Baru', false)
        ->assertDontSee('Shopee Lama', false);
});

it('returns store list json with mapped flags for create form', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 5,
        'jubelio_store_name' => 'Mapped Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'BSD',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Http::fake([
        'https://api2.jubelio.com/locations/10' => Http::response([
            'stores' => [
                ['store_id' => 5, 'store_name' => 'Mapped Store'],
                ['store_id' => 6, 'store_name' => 'Fresh Store'],
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('jubelio.sync.locationStores', ['locationId' => 10]))
        ->assertSuccessful()
        ->assertJsonPath('stores.0.mapped', true)
        ->assertJsonPath('stores.1.mapped', false)
        ->assertJsonPath('stores.1.store_name', 'Fresh Store');
});

it('creates mapping only for selected store ids', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Http::fake([
        'https://api2.jubelio.com/locations/10' => Http::response([
            'stores' => [
                ['store_id' => 1, 'store_name' => 'Store One'],
                ['store_id' => 2, 'store_name' => 'Store Two'],
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('jubelio.sync.store'), [
            'location_id' => 10,
            'location_name' => 'BSD Online',
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'store_ids' => [2],
        ])
        ->assertRedirect(route('jubelio.sync.index'))
        ->assertSessionHas('success');

    expect(Jubeliosync::count())->toBe(1)
        ->and(Jubeliosync::first()->jubelio_store_id)->toBe(2)
        ->and(Jubeliosync::first()->jubelio_store_name)->toBe('Store Two');
});

it('extracts stores from warehouse or stores payload keys', function () {
    $service = app(JubelioSyncCatalogService::class);

    $fromStores = $service->extractStoresFromLocationPayload([
        'stores' => [['store_id' => 3, 'store_name' => 'A']],
    ]);

    $fromWarehouse = $service->extractStoresFromLocationPayload([
        'warehouse' => [['store_id' => 4, 'store_name' => 'B']],
    ]);

    expect($fromStores)->toBe([['store_id' => 3, 'store_name' => 'A']])
        ->and($fromWarehouse)->toBe([['store_id' => 4, 'store_name' => 'B']]);
});
