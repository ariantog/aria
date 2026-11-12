<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use App\Models\User;
use App\Services\Jubelio\JubelioOrderReadyToShip;
use Spatie\Permission\Models\Permission;

it('stores mobile resi scan with READY TO SHIP status', function () {
    $order = Jubelioorder::create([
        'jubelio_order_id' => 'mobile-scan:SP-RTS-1',
        'source' => 3,
        'invoice' => 'SP-RTS-1',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'user_id' => User::factory()->create()->id,
        'status' => 0,
    ]);

    expect(JubelioOrderReadyToShip::isHeldForCourier($order))->toBeTrue();
});

it('does not process READY TO SHIP orders via ProcessJubelioOrder', function () {
    $order = Jubelioorder::create([
        'jubelio_order_id' => 'mobile-scan:SP-RTS-PROC',
        'source' => 3,
        'invoice' => 'SP-RTS-PROC',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('READY TO SHIP')
        ->and($order->fresh()->run_count)->toBe(0)
        ->and($order->fresh()->status)->toBe(0);
});

it('promotes READY TO SHIP row to SHIPPED when webhook arrives', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    Jubelioorder::create([
        'jubelio_order_id' => 'mobile-scan:SP-WH-UPGRADE',
        'source' => 3,
        'invoice' => 'SP-WH-UPGRADE',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'user_id' => 5,
        'status' => 0,
    ]);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'jubelio-999',
        'salesorder_no' => 'SP-WH-UPGRADE',
        'transaction_date' => jubelioRecentTransactionDateOnly(),
    ]);

    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful();

    $order = Jubelioorder::where('invoice', 'SP-WH-UPGRADE')->first();
    expect($order)->not->toBeNull()
        ->and($order->order_status)->toBe('SHIPPED')
        ->and($order->jubelio_order_id)->toBe('jubelio-999')
        ->and((int) $order->user_id)->toBe(5)
        ->and(Jubelioorder::where('invoice', 'SP-WH-UPGRADE')->count())->toBe(1);
});

it('shows scanner on jubelio order detail when user_id is set', function () {
    Permission::findOrCreate('jubelio-view', 'web');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('jubelio-view');

    $user = User::factory()->create(['name' => 'Scanner User']);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'mobile-scan:SP-VIEW',
        'source' => 3,
        'invoice' => 'SP-VIEW',
        'type' => 'SELL',
        'order_status' => JubelioOrderReadyToShip::STATUS,
        'run_count' => 0,
        'user_id' => $user->id,
        'status' => 0,
    ]);

    $this->actingAs($viewer)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee('Scan resi', false)
        ->assertSee('Scanner User', false);
});
