<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\Jubelio\JubelioTrackingLookup;
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
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->with('JP1234567890', 88)
            ->andReturn([
                'status' => 'found',
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
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->andReturn([
                'status' => 'not_found',
                'message' => 'Resi tidak ditemukan di Jubelio.',
            ]);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'tokopedia',
        'barcodes' => ['UNKNOWN-AWB'],
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Resi tidak ditemukan di Jubelio.')
        ->assertJsonPath('skipped.0.reason', 'Resi tidak ditemukan di Jubelio.');

    expect(Jubelioorder::count())->toBe(0);
});

it('resolves tracking via jubelio sales order query only', function () {
    config(['jubelio_resi_scan.tracking_search_days' => 21]);

    $service = Mockery::mock(JubelioService::class)->makePartial();
    $service->shouldReceive('resetOrdersQueryProbe')->once();
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->once()
        ->with(1, 50, ['tracking_no' => 'JNE998877'])
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

    $result = (new JubelioTrackingLookup($service))->resolve('jne 998877', 12);

    expect($result['status'])->toBe('found')
        ->and($result['salesorder_no'])->toBe('TP-ORDER-501')
        ->and($result['salesorder_id'])->toBe(501);
});

it('confirms tracking via sales order detail when list row omits awb', function () {
    config(['jubelio_resi_scan.tracking_search_days' => 21]);

    $service = Mockery::mock(JubelioService::class)->makePartial();
    $service->shouldReceive('resetOrdersQueryProbe')->once();
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->once()
        ->with(1, 50, ['tracking_no' => 'JNE111222'])
        ->andReturn([
            'totalCount' => 1,
            'data' => [
                [
                    'salesorder_id' => 777,
                    'salesorder_no' => 'TT-DETAIL-777',
                    'store_id' => 3,
                ],
            ],
        ]);
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->with(1, 50, ['trackingNo' => 'JNE111222'])
        ->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->with(1, 50, ['airwaybill_no' => 'JNE111222'])
        ->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('fetchSalesOrder')
        ->once()
        ->with(777)
        ->andReturn([
            'salesorder_id' => 777,
            'salesorder_no' => 'TT-DETAIL-777',
            'shipments' => [
                ['tracking_no' => 'JNE111222'],
            ],
        ]);

    $result = (new JubelioTrackingLookup($service))->resolve('JNE111222', 3);

    expect($result['status'])->toBe('found')
        ->and($result['salesorder_no'])->toBe('TT-DETAIL-777')
        ->and($result['salesorder_id'])->toBe(777);
});
