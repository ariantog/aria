<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\JubelioService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('jubelio-view', 'web');
    config(['services.jubelio.active' => true]);
});

it('resolves tiktok resi to jubelio salesorder_no via tracking lookup', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 88,
        'jubelio_store_name' => 'TikTok Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSalesOrderByTrackingNo')
            ->once()
            ->with('JP1234567890', 88, \Mockery::type('int'), \Mockery::type('int'))
            ->andReturn([
                'salesorder_no' => 'TT-586928090015303551-32327',
                'salesorder_id' => 'so-tt-1',
            ]);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'tiktok',
        'barcodes' => ['JP1234567890'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('created.0.invoice', 'TT-586928090015303551-32327');

    $order = Jubelioorder::where('invoice', 'TT-586928090015303551-32327')->first();
    expect($order)->not->toBeNull()
        ->and($order->jubelio_order_id)->toBe('so-tt-1');
});

it('skips tiktok resi when tracking is not found in jubelio', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 9,
        'jubelio_store_name' => 'Tokopedia Shop',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'BSD',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 2,
        'bin_id' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSalesOrderByTrackingNo')
            ->once()
            ->andReturn(null);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'tokopedia',
        'barcodes' => ['UNKNOWN-AWB'],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('skipped.0.reason', 'Resi tidak ditemukan di Jubelio.');

    expect(Jubelioorder::count())->toBe(0);
});

it('matches tracking numbers on jubelio sales order list rows', function () {
    $service = Mockery::mock(JubelioService::class)->makePartial();
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->once()
        ->with(1, 50, ['tracking_no' => 'JNE998877'])
        ->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->once()
        ->with(1, 50, ['trackingNo' => 'JNE998877'])
        ->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->once()
        ->with(1, 50, ['airwaybill_no' => 'JNE998877'])
        ->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('fetchSalesOrders')
        ->once()
        ->andReturn([
            'totalCount' => 1,
            'data' => [
                [
                    'salesorder_id' => 501,
                    'salesorder_no' => 'TP-ORDER-501',
                    'store_id' => 12,
                    'tracking_no' => 'JNE998877',
                ],
            ],
        ]);

    $this->instance(JubelioService::class, $service);

    $match = $service->findSalesOrderByTrackingNo('jne 998877', 12, 14, 1);

    expect($match)->toBe([
        'salesorder_no' => 'TP-ORDER-501',
        'salesorder_id' => 501,
    ]);
});
