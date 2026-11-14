<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;
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

it('queues sell orders from mobile shopee resi scan via jubelio awb lookup', function () {
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

    $response->assertStatus(201)
        ->assertJsonPath('created.0.invoice', 'SP-250302JA65X2S0')
        ->assertJsonPath('created.1.invoice', 'SP-SECOND-ORDER');

    $order = Jubelioorder::where('invoice', 'SP-250302JA65X2S0')->first();
    expect($order)->not->toBeNull()
        ->and($order->type)->toBe('SELL')
        ->and($order->order_status)->toBe('READY TO SHIP')
        ->and((int) $order->source)->toBe(3)
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

it('skips duplicate invoices on resi scan submit', function () {
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

    Jubelioorder::create([
        'jubelio_order_id' => 'existing-tt',
        'source' => 1,
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
        ->assertJsonCount(1, 'skipped')
        ->assertJsonPath('created.0.invoice', 'TT-NEW-TT-001')
        ->assertJsonPath('skipped.0.invoice', 'TT-DUP-TEST');
});

it('formats invoice prefixes via service', function () {
    $service = app(JubelioResiScanService::class);

    expect($service->formatInvoice('shopee', 'ABC123'))->toBe('SP-ABC123')
        ->and($service->formatInvoice('shopee', 'sp-abc123'))->toBe('SP-abc123')
        ->and($service->formatInvoice('tiktok', 'TT-999'))->toBe('TT-999');
});

it('exposes scan_resi in mobile actions when jubelio-view is granted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/v1/me');

    $response->assertSuccessful();
    expect($response->json('mobile_actions'))->toContain('scan_resi');
});
