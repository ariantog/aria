<?php

use App\Models\Addrbook;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\User;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config([
        'services.jubelio.active' => true,
        'services.jubelio.verify_ssl' => false,
    ]);
});

it('refreshes default bin ids for all mapped jubelio locations', function () {
    Permission::firstOrCreate(['name' => 'jubelio-sync']);
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-sync');

    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'test-token',
            'expires_at' => now()->addHour()->toDateTimeString(),
        ],
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store A',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Gudang A',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 2,
        'jubelio_store_name' => 'Store B',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Gudang A',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 3,
        'jubelio_store_name' => 'Store C',
        'jubelio_location_id' => 20,
        'jubelio_location_name' => 'Gudang B',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Http::fake([
        'https://api2.jubelio.com/wms/default-bin/10' => Http::response(['bin_id' => 42]),
        'https://api2.jubelio.com/wms/default-bin/20' => Http::response(['bin_id' => 99]),
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.sync.refreshBins'))
        ->assertRedirect(route('jubelio.sync.index'))
        ->assertSessionHas('success');

    expect(Jubeliosync::where('jubelio_location_id', 10)->pluck('bin_id')->unique()->all())->toBe([42])
        ->and((int) Jubeliosync::where('jubelio_location_id', 20)->value('bin_id'))->toBe(99);
});

it('shows refresh all bins button on jubelio sync index', function () {
    Permission::firstOrCreate(['name' => 'jubelio-sync']);
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-sync');

    $this->actingAs($user)
        ->get(route('jubelio.sync.index'))
        ->assertSuccessful()
        ->assertSee('Cek semua bin', false)
        ->assertSee(route('jubelio.sync.refreshBins'), false);
});
