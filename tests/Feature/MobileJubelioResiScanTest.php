<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\Jubelio\JubelioOrderReadyToShip;
use App\Services\Jubelio\JubelioResiScanService;
use App\Services\JubelioService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('jubelio-view', 'web');
});

it('requires jubelio-view permission for resi scan options', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/v1/jubelio/resi-scan/options')
        ->assertForbidden();
});

it('updates queued rows from mobile shopee resi scan after awb resolves invoice', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Scan']);

    Jubeliosync::create([
        'jubelio_store_id' => 101,
        'jubelio_store_name' => 'Shopee Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'shopee-push:250302JA65X2S0',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-250302JA65X2S0',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'warehouse_id' => $warehouse->id,
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'shopee-push:SECOND-ORDER',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-SECOND-ORDER',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'status' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->twice()
            ->andReturnUsing(function (string $tracking) {
                return match ($tracking) {
                    'SPX1234567890' => [
                        'status' => 'found',
                        'salesorder_no' => 'SP-250302JA65X2S0',
                        'salesorder_id' => 'so-shopee-1',
                    ],
                    'JNE9988776655' => [
                        'status' => 'found',
                        'salesorder_no' => 'SP-SECOND-ORDER',
                        'salesorder_id' => 'so-shopee-2',
                    ],
                    default => [
                        'status' => 'not_found',
                        'message' => 'Resi tidak ditemukan di Jubelio.',
                    ],
                };
            });
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'shopee',
        'barcodes' => ['SPX1234567890', 'JNE9988776655'],
        'remember_defaults' => true,
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(2, 'updated')
        ->assertJsonPath('updated.0.invoice', 'SP-250302JA65X2S0')
        ->assertJsonPath('updated.1.invoice', 'SP-SECOND-ORDER');

    expect(Jubelioorder::where('type', 'SELL')->count())->toBe(2);

    $order = Jubelioorder::where('invoice', 'SP-250302JA65X2S0')->first();
    expect($order)->not->toBeNull()
        ->and($order->type)->toBe('SELL')
        ->and($order->order_status)->toBe('READY TO SHIP')
        ->and($order->source)->toBe(Jubelioorder::SOURCE_SHOPEE_PUSH)
        ->and((int) $order->user_id)->toBe($user->id)
        ->and((int) $order->warehouse_id)->toBe($warehouse->id)
        ->and((int) $order->jubelio_store_id)->toBe(101)
        ->and((int) $order->jubelio_location_id)->toBe(-1)
        ->and($order->status)->toBe(0);

    $defaults = $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/v1/jubelio/resi-scan/options')
        ->json('defaults');

    expect($defaults['warehouse_id'])->toBe($warehouse->id)
        ->and($defaults['marketplace'])->toBe('shopee');
});

it('updates existing queue row on resi scan instead of inserting duplicate', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 5,
        'jubelio_store_name' => 'TikTok Shop',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'BSD',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 2,
        'bin_id' => 0,
    ]);

    $preexisting = Jubelioorder::create([
        'jubelio_order_id' => 'existing-tt',
        'source' => Jubelioorder::SOURCE_WEBHOOK,
        'invoice' => 'TT-DUP-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->twice()
            ->andReturnUsing(function (string $tracking) {
                return match ($tracking) {
                    'DUP-TEST' => [
                        'status' => 'found',
                        'salesorder_no' => 'TT-DUP-TEST',
                        'salesorder_id' => 'dup-so',
                    ],
                    'NEW-TT-001' => [
                        'status' => 'found',
                        'salesorder_no' => 'TT-NEW-TT-001',
                        'salesorder_id' => 'new-so',
                    ],
                    default => [
                        'status' => 'not_found',
                        'message' => 'Resi tidak ditemukan di Jubelio.',
                    ],
                };
            });
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'tiktok',
        'barcodes' => ['DUP-TEST', 'NEW-TT-001'],
    ]);

    $response->assertCreated()
        ->assertJsonCount(1, 'created')
        ->assertJsonCount(1, 'updated')
        ->assertJsonCount(0, 'skipped')
        ->assertJsonPath('updated.0.invoice', 'TT-DUP-TEST')
        ->assertJsonPath('created.0.invoice', 'TT-NEW-TT-001');

    expect(Jubelioorder::where('invoice', 'TT-DUP-TEST')->count())->toBe(1)
        ->and(Jubelioorder::where('invoice', 'TT-NEW-TT-001')->count())->toBe(1);

    $preexisting->refresh();
    expect($preexisting->order_status)->toBe(JubelioOrderReadyToShip::STATUS)
        ->and((int) $preexisting->user_id)->toBe($user->id)
        ->and((int) $preexisting->warehouse_id)->toBe($warehouse->id);
});

it('updates shopee push placeholder on resi scan without duplicating jubelioorders', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 101,
        'jubelio_store_name' => 'Shopee Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'shopee-push:PRECHECK_1',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-PRECHECK_1',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'status' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->andReturn([
                'status' => 'found',
                'salesorder_no' => 'SP-PRECHECK_1',
                'salesorder_id' => 'jubelio-so-real',
            ]);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'shopee',
        'barcodes' => ['SPX-PRECHECK'],
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(1, 'updated')
        ->assertJsonPath('updated.0.invoice', 'SP-PRECHECK_1');

    expect(Jubelioorder::where('invoice', 'SP-PRECHECK_1')->count())->toBe(1);

    $order = Jubelioorder::where('invoice', 'SP-PRECHECK_1')->first();
    expect($order->jubelio_order_id)->toBe('jubelio-so-real')
        ->and($order->source)->toBe(Jubelioorder::SOURCE_SHOPEE_PUSH)
        ->and((int) $order->user_id)->toBe($user->id);
});

it('formats invoice prefixes via service', function () {
    $service = app(JubelioResiScanService::class);

    expect($service->formatInvoice('shopee', 'ABC123'))->toBe('SP-ABC123')
        ->and($service->formatInvoice('shopee', 'sp-abc123'))->toBe('SP-abc123')
        ->and($service->formatInvoice('tiktok', 'TT-999'))->toBe('TT-999');
});

it('creates tiktok queue row when awb resolves and no queue row exists', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 32327,
        'jubelio_store_name' => 'TikTok Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->andReturn([
                'status' => 'found',
                'salesorder_no' => 'TT-586928090015303551-32327',
                'salesorder_id' => 'so-tt-new',
            ]);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'tiktok',
        'barcodes' => ['JP1234567890'],
    ]);

    $response->assertCreated()
        ->assertJsonCount(1, 'created')
        ->assertJsonPath('created.0.invoice', 'TT-586928090015303551-32327');

    $order = Jubelioorder::where('invoice', 'TT-586928090015303551-32327')->first();
    expect($order)->not->toBeNull()
        ->and($order->jubelio_order_id)->toBe('so-tt-new')
        ->and($order->order_status)->toBe(JubelioOrderReadyToShip::STATUS)
        ->and($order->source)->toBe(Jubelioorder::SOURCE_SCAN_RESI)
        ->and((int) $order->user_id)->toBe($user->id);
});

it('updates tiktok queue row when invoice omits jubelio store suffix', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 32327,
        'jubelio_store_name' => 'TikTok Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'legacy-tt',
        'source' => Jubelioorder::SOURCE_WEBHOOK,
        'invoice' => 'TT-586928090015303551',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
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

    $response->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(1, 'updated')
        ->assertJsonPath('updated.0.invoice', 'TT-586928090015303551');

    expect(Jubelioorder::where('type', 'SELL')->count())->toBe(1);
});

it('does not insert jubelioorders when awb resolves but queue row is missing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 101,
        'jubelio_store_name' => 'Shopee Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    config(['services.jubelio.active' => true]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->andReturn([
                'status' => 'found',
                'salesorder_no' => 'SP-NO-QUEUE-ROW',
                'salesorder_id' => 'so-missing-queue',
            ]);
    });

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'shopee',
        'barcodes' => ['SPX-NO-QUEUE'],
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(0, 'updated')
        ->assertJsonCount(1, 'skipped')
        ->assertJsonPath('skipped.0.invoice', 'SP-NO-QUEUE-ROW');

    expect(Jubelioorder::count())->toBe(0);
});

it('updates lazada queue row when invoice already exists', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $warehouse = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 18697,
        'jubelio_store_name' => 'Lazada Crystal',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'webhook-lz',
        'source' => Jubelioorder::SOURCE_WEBHOOK,
        'invoice' => 'LZ-2706952969613265-18697',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 18697,
        'status' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/jubelio/resi-scan', [
        'warehouse_id' => $warehouse->id,
        'marketplace' => 'lazada',
        'barcodes' => ['2706952969613265'],
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(1, 'updated')
        ->assertJsonPath('updated.0.invoice', 'LZ-2706952969613265-18697');

    expect(Jubelioorder::where('invoice', 'LZ-2706952969613265-18697')->count())->toBe(1);

    $order = Jubelioorder::where('invoice', 'LZ-2706952969613265-18697')->first();
    expect((int) $order->jubelio_store_id)->toBe(18697)
        ->and($order->order_status)->toBe(JubelioOrderReadyToShip::STATUS);
});

it('does not double-append lazada store id when barcode already includes suffix', function () {
    $service = app(JubelioResiScanService::class);

    expect($service->formatInvoice('lazada', 'LZ-2706952969613265-18697', 18697))
        ->toBe('LZ-2706952969613265-18697')
        ->and($service->formatInvoice('lazada', '2706952969613265', 18697))
        ->toBe('LZ-2706952969613265-18697');
});

it('exposes scan_resi in mobile actions when jubelio-view is granted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/v1/me');

    $response->assertSuccessful();
    expect($response->json('mobile_actions'))->toContain('scan_resi');
});
