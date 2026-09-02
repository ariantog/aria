<?php

use App\Jobs\SyncJubelioMissingOrders;
use App\Models\Addrbook;
use App\Models\Crongetorder;
use App\Models\Jubelioorder;
use App\Models\ScheduledTask;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JubelioGetOrdersService;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

function seedGetOrdersScheduledTask(): ScheduledTask
{
    return ScheduledTask::create([
        'command' => 'jubelio:get-orders',
        'name' => 'Jubelio Get Orders (legacy resume)',
        'frequency' => 'everyMinute',
        'active' => false,
        'description' => 'Test task',
    ]);
}

it('renders get orders page with start form when no import exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.get-orders.index'))
        ->assertSuccessful()
        ->assertSee('Jubelio Get Orders')
        ->assertSee('Mulai Sinkronisasi');
});

it('starts a get orders sync and dispatches background job', function () {
    Queue::fake();
    $task = seedGetOrdersScheduledTask();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.store'), [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-03',
        ])
        ->assertRedirect(route('jubelio.get-orders.index'));

    expect(Crongetorder::count())->toBe(1);
    $import = Crongetorder::first();
    expect($import->from->toDateString())->toBe('2026-08-01');
    expect($import->to)->toBe(2);
    expect($task->fresh()->active)->toBeTrue();

    Queue::assertPushed(SyncJubelioMissingOrders::class, fn ($job) => $job->importId === $import->id);
});

it('fetches api pages and queues only eligible missing orders', function () {
    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->once()
            ->with(1, 200, \Mockery::type('string'), \Mockery::type('string'))
            ->andReturn([
                'totalCount' => 3,
                'data' => [
                    [
                        'salesorder_id' => 'so-100',
                        'salesorder_no' => 'INV-SHIPPED',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'N',
                    ],
                    [
                        'salesorder_id' => 'so-101',
                        'salesorder_no' => 'INV-DRAFT',
                        'internal_status' => 'DRAFT',
                        'is_canceled' => 'N',
                    ],
                    [
                        'salesorder_id' => 'so-102',
                        'salesorder_no' => 'INV-CANCELED',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'Y',
                    ],
                ],
            ]);
    });

    $this->artisan('jubelio:get-orders', ['--sync' => true])->assertSuccessful();

    $import->refresh();
    expect($import->status)->toBe(1);
    expect($import->orders_queued)->toBe(1);

    $order = Jubelioorder::where('invoice', 'INV-SHIPPED')->first();
    expect($order)->not->toBeNull();
    expect($order->source)->toBe(2);
    expect(Jubelioorder::where('invoice', 'INV-DRAFT')->exists())->toBeFalse();
});

it('skips orders already present in aria when syncing', function () {
    seedGetOrdersScheduledTask();

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 1,
        'count' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->once()
            ->andReturn([
                'totalCount' => 2,
                'data' => [
                    [
                        'salesorder_id' => 'so-1',
                        'salesorder_no' => 'INV-EXISTS',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'N',
                    ],
                    [
                        'salesorder_id' => 'so-2',
                        'salesorder_no' => 'INV-MISSING',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'N',
                    ],
                ],
            ]);
    });

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'INV-EXISTS',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => '2026-08-01',
    ]);

    $this->artisan('jubelio:get-orders', ['--sync' => true])->assertSuccessful();

    $import->refresh();
    expect($import->status)->toBe(1);
    expect($import->orders_queued)->toBe(1);
    expect(Jubelioorder::where('invoice', 'INV-MISSING')->exists())->toBeTrue();
    expect(Jubelioorder::where('invoice', 'INV-EXISTS')->exists())->toBeFalse();
});

it('can fetch multiple pages in one cron run', function () {
    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->twice()
            ->andReturn(
                [
                    'totalCount' => 400,
                    'data' => [[
                        'salesorder_id' => 'so-page-1',
                        'salesorder_no' => 'INV-P1',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'N',
                    ]],
                ],
                [
                    'totalCount' => 400,
                    'data' => [[
                        'salesorder_id' => 'so-page-2',
                        'salesorder_no' => 'INV-P2',
                        'internal_status' => 'SHIPPED',
                        'is_canceled' => 'N',
                    ]],
                ],
            );
    });

    $this->artisan('jubelio:get-orders', ['--pages' => 2])->assertSuccessful();

    $import->refresh();
    expect($import->count)->toBe(2);
    expect($import->total)->toBe(2);
    expect($import->status)->toBe(1);
    expect($import->orders_queued)->toBe(2);
    expect(Jubelioorder::whereIn('invoice', ['INV-P1', 'INV-P2'])->count())->toBe(2);
});

it('advances page count when api returns an empty data page', function () {
    seedGetOrdersScheduledTask();

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 3,
        'count' => 2,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->once()
            ->with(3, 200, \Mockery::type('string'), \Mockery::type('string'))
            ->andReturn([
                'totalCount' => 600,
                'data' => [],
            ]);
    });

    $this->artisan('jubelio:get-orders', ['--pages' => 1])->assertSuccessful();

    $import->refresh();
    expect($import->count)->toBe(3);
    expect($import->status)->toBe(1);
});

it('resumes a full sync from the next unread page', function () {
    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 2,
        'count' => 1,
        'status' => 0,
        'orders_queued' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->once()
            ->with(2, 200, \Mockery::type('string'), \Mockery::type('string'))
            ->andReturn([
                'totalCount' => 400,
                'data' => [[
                    'salesorder_id' => 'so-page-2',
                    'salesorder_no' => 'INV-RESUME',
                    'internal_status' => 'SHIPPED',
                    'is_canceled' => 'N',
                ]],
            ]);
    });

    $this->artisan('jubelio:get-orders', ['--sync' => true])->assertSuccessful();

    $import->refresh();
    expect($import->count)->toBe(2);
    expect($import->status)->toBe(1);
    expect($import->orders_queued)->toBe(1);
    expect(Jubelioorder::where('invoice', 'INV-RESUME')->exists())->toBeTrue();
});

it('re-queues a short batch when the import is not finished', function () {
    Queue::fake();
    $task = seedGetOrdersScheduledTask();

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 20,
        'count' => 7,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->andReturn([
                'totalCount' => 4000,
                'data' => [],
            ]);
    });

    (new SyncJubelioMissingOrders($import->id))->handle(app(JubelioGetOrdersService::class));

    $import->refresh();
    expect($import->count)->toBe(10);
    expect($import->status)->toBe(0);
    expect($task->fresh()->active)->toBeTrue();

    Queue::assertPushed(SyncJubelioMissingOrders::class, fn ($job) => $job->importId === $import->id);
});

it('records an error when the jubelio list api fails', function () {
    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 10,
        'count' => 7,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')->once()->andReturn(null);
    });

    $this->artisan('jubelio:get-orders', ['--pages' => 1])->assertFailed();

    $import->refresh();
    expect($import->status)->toBe(0);
    expect($import->count)->toBe(7);
    expect($import->last_error)->toContain('halaman 8');
});

it('resumes a stuck import from the ui', function () {
    Queue::fake();
    $task = seedGetOrdersScheduledTask();

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 10,
        'count' => 7,
        'status' => 0,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.resume'))
        ->assertRedirect(route('jubelio.get-orders.index'));

    expect($task->fresh()->active)->toBeTrue();
    Queue::assertPushed(SyncJubelioMissingOrders::class, fn ($job) => $job->importId === $import->id);
});

it('shows resume control while an import is running', function () {
    Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 10,
        'count' => 7,
        'status' => 0,
        'orders_queued' => 0,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.get-orders.index'))
        ->assertSuccessful()
        ->assertSee('Halaman 7/10', false)
        ->assertSee('Lanjutkan');
});

it('resets an import', function () {
    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.reset'))
        ->assertRedirect();

    expect(Crongetorder::count())->toBe(0);
});

it('polls recent days via dedicated command', function () {
    config(['services.jubelio.active' => true, 'services.jubelio.poll_days' => 7]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrders')
            ->once()
            ->andReturn([
                'totalCount' => 1,
                'data' => [[
                    'salesorder_id' => 'so-poll',
                    'salesorder_no' => 'INV-POLL',
                    'internal_status' => 'SHIPPED',
                    'is_canceled' => 'N',
                ]],
            ]);
    });

    $this->artisan('jubelio:poll-missing-orders')->assertSuccessful();

    expect(Jubelioorder::where('invoice', 'INV-POLL')->exists())->toBeTrue();
});

it('skips ineligible orders during service reconcile helper', function () {
    $service = app(JubelioGetOrdersService::class);

    $queued = $service->queueEligibleRows([
        [
            'salesorder_id' => 'so-keep',
            'salesorder_no' => 'INV-KEEP',
            'internal_status' => 'SHIPPED',
            'is_canceled' => 'N',
        ],
        [
            'salesorder_id' => 'so-drop',
            'salesorder_no' => 'INV-DROP-STATUS',
            'internal_status' => 'DRAFT',
            'is_canceled' => 'N',
        ],
    ]);

    expect($queued)->toBe(1);
    expect(Jubelioorder::where('invoice', 'INV-KEEP')->exists())->toBeTrue();
    expect(Jubelioorder::where('invoice', 'INV-DROP-STATUS')->exists())->toBeFalse();
});

it('does not duplicate when order already in jubelioorders', function () {
    Jubelioorder::create([
        'jubelio_order_id' => 'jo-1',
        'source' => 1,
        'invoice' => 'INV-DROP-JO',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $service = app(JubelioGetOrdersService::class);
    $queued = $service->queueEligibleRows([
        [
            'salesorder_id' => 'so-dup',
            'salesorder_no' => 'INV-DROP-JO',
            'internal_status' => 'SHIPPED',
            'is_canceled' => 'N',
        ],
    ]);

    expect($queued)->toBe(0);
    expect(Jubelioorder::where('invoice', 'INV-DROP-JO')->count())->toBe(1);
});
