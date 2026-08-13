<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('shows jubelio cron transactions on the transactions index for location-scoped users', function () {
    Permission::firstOrCreate(['name' => 'transactions-list']);

    $location = Location::create(['name' => 'Store A']);
    $otherLocation = Location::create(['name' => 'Store B']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-JUB-LIST']);

    $warehouse->locations()->attach($location->id);

    Jubeliosync::create([
        'jubelio_store_id' => 10,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 20,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'cron-list-1',
        'source' => 1,
        'invoice' => 'INV-JUB-LIST-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => json_encode([
            'salesorder_no' => 'INV-JUB-LIST-1',
            'store_id' => 10,
            'location_id' => 20,
            'sub_total' => 100000,
            'real_total' => 100000,
            'transaction_date' => '2026-05-10',
            'items' => [
                ['item_code' => 'SKU-JUB-LIST', 'qty' => 1, 'price' => 100000],
            ],
        ]),
        'status' => 0,
    ]);

    app(ProcessJubelioOrder::class)->execute($order);

    $cronTransaction = Transaction::where('invoice', 'INV-JUB-LIST-1')->first();
    expect($cronTransaction)->not->toBeNull();
    expect($cronTransaction->user_id)->toBe(Transaction::resolveJubelioCronUserId());

    $user = User::factory()->create(['location_id' => $location->id]);
    $user->givePermissionTo('transactions-list');

    $hiddenUser = User::factory()->create(['location_id' => $otherLocation->id]);
    $hiddenUser->givePermissionTo('transactions-list');

    expect(Transaction::where('invoice', 'INV-JUB-LIST-1')->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertSuccessful()
        ->assertSee('INV-JUB-LIST-1');

    $this->actingAs($hiddenUser)
        ->get(route('transactions.index'))
        ->assertSuccessful()
        ->assertDontSee('INV-JUB-LIST-1');

    $this->actingAs($user)
        ->get(route('transactions.index', ['invoice' => 'INV-JUB-LIST-1']))
        ->assertSuccessful()
        ->assertSee('INV-JUB-LIST-1');
});

it('backfills jubelio party locations via artisan command', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Location::create(['name' => 'HQ']);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 2,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $this->artisan('jubelio:ensure-party-locations')->assertSuccessful();

    expect($warehouse->locations()->exists())->toBeTrue()
        ->and($customer->locations()->exists())->toBeTrue();
});
