<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JubelioGetOrdersService;
use App\Services\JubelioService;
use Mockery\MockInterface;

it('renders cek order page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.order.cek'))
        ->assertSuccessful()
        ->assertSee('Jubelio Cek Order')
        ->assertSee('Jubelio Order ID');
});

it('looks up an order from jubelio api', function () {
    $user = User::factory()->create();

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->with('so-123')
            ->andReturn([
                'salesorder_id' => 'so-123',
                'salesorder_no' => 'INV-CEK-123',
                'status' => 'SHIPPED',
                'source_name' => 'Shopee',
                'location_name' => 'Gudang A',
            ]);
    });

    $this->actingAs($user)
        ->get(route('jubelio.order.cek', ['order_id' => 'so-123']))
        ->assertSuccessful()
        ->assertSee('INV-CEK-123')
        ->assertSee('data-testid="jubelio-cek-queue"', false);
});

it('disables queue button when invoice exists in transactions', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-EXISTS-TX',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->andReturn([
                'salesorder_id' => 'so-tx',
                'salesorder_no' => 'INV-EXISTS-TX',
                'status' => 'SHIPPED',
            ]);
    });

    $this->actingAs($user)
        ->get(route('jubelio.order.cek', ['order_id' => 'so-tx']))
        ->assertSuccessful()
        ->assertSee('Invoice sudah ada di tabel transaksi', false)
        ->assertSee('data-testid="jubelio-cek-queue-disabled"', false);
});

it('disables queue button when order already in jubelioorders queue', function () {
    $user = User::factory()->create();

    Jubelioorder::create([
        'jubelio_order_id' => 'so-queued',
        'source' => 1,
        'invoice' => 'INV-QUEUED',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->andReturn([
                'salesorder_id' => 'so-queued',
                'salesorder_no' => 'INV-QUEUED',
                'status' => 'SHIPPED',
            ]);
    });

    $this->actingAs($user)
        ->get(route('jubelio.order.cek', ['order_id' => 'so-queued']))
        ->assertSuccessful()
        ->assertSee('Sudah di antrian Jubelio Orders?')
        ->assertSee('data-testid="jubelio-cek-queue-disabled"', false);
});

it('queues a missing order via post', function () {
    $user = User::factory()->create();

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->with('so-new')
            ->andReturn([
                'salesorder_id' => 'so-new',
                'salesorder_no' => 'INV-NEW-QUEUE',
                'status' => 'SHIPPED',
            ]);
    });

    $this->actingAs($user)
        ->post(route('jubelio.order.cek.queue'), ['order_id' => 'so-new'])
        ->assertRedirect(route('jubelio.order.cek', ['order_id' => 'so-new']))
        ->assertSessionHas('success');

    expect(Jubelioorder::where('invoice', 'INV-NEW-QUEUE')->exists())->toBeTrue();
});

it('rejects queue when invoice exists in transactions', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-BLOCK-TX',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->andReturn([
                'salesorder_id' => 'so-block',
                'salesorder_no' => 'INV-BLOCK-TX',
                'status' => 'SHIPPED',
            ]);
    });

    $this->actingAs($user)
        ->post(route('jubelio.order.cek.queue'), ['order_id' => 'so-block'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Jubelioorder::where('invoice', 'INV-BLOCK-TX')->exists())->toBeFalse();
});

it('rejects queue when order already in jubelioorders', function () {
    $user = User::factory()->create();

    Jubelioorder::create([
        'jubelio_order_id' => 'so-dup',
        'source' => 1,
        'invoice' => 'INV-DUP',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->andReturn([
                'salesorder_id' => 'so-dup',
                'salesorder_no' => 'INV-DUP',
                'status' => 'SHIPPED',
            ]);
    });

    $this->actingAs($user)
        ->post(route('jubelio.order.cek.queue'), ['order_id' => 'so-dup'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Jubelioorder::where('jubelio_order_id', 'so-dup')->count())->toBe(1);
});

it('inspect service blocks queue for transaction and existing queue separately', function () {
    $service = app(JubelioGetOrdersService::class);

    $inspection = $service->inspectApiOrder([
        'salesorder_id' => 'so-1',
        'salesorder_no' => 'INV-1',
        'status' => 'SHIPPED',
    ]);

    expect($inspection['can_queue'])->toBeTrue();

    Jubelioorder::create([
        'jubelio_order_id' => 'so-1',
        'source' => 1,
        'invoice' => 'INV-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $inspection = $service->inspectApiOrder([
        'salesorder_id' => 'so-1',
        'salesorder_no' => 'INV-1',
        'status' => 'SHIPPED',
    ]);

    expect($inspection['in_queue'])->toBeTrue()
        ->and($inspection['can_queue'])->toBeFalse();
});
