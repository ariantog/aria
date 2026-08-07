<?php

use App\Models\Addrbook;
use App\Models\Crongetorder;
use App\Models\Crongetorderdetail;
use App\Models\Jubelioorder;
use App\Models\ScheduledTask;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JubelioGetOrdersService;
use App\Services\JubelioService;
use Mockery\MockInterface;

function seedGetOrdersScheduledTask(): ScheduledTask
{
    return ScheduledTask::create([
        'command' => 'jubelio:get-orders',
        'name' => 'Jubelio Get Orders (API backfill)',
        'frequency' => 'everyMinute',
        'is_active' => false,
        'description' => 'Test task',
    ]);
}

it('renders get orders page with start form when no import exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.get-orders.index'))
        ->assertSuccessful()
        ->assertSee('Jubelio Get Orders')
        ->assertSee('Mulai Import');
});

it('starts a get orders import and enables the scheduled task', function () {
    $user = User::factory()->create();
    $task = seedGetOrdersScheduledTask();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.store'), [
            'from' => '2026-08-01',
            'to' => 2,
        ])
        ->assertRedirect(route('jubelio.get-orders.index'));

    expect(Crongetorder::count())->toBe(1);
    expect($task->fresh()->is_active)->toBeTrue();
});

it('fetches api pages and skips ineligible orders at insert time', function () {
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

    $this->artisan('jubelio:get-orders')->assertSuccessful();

    $import->refresh();
    expect($import->status)->toBe(1);
    expect(Crongetorderdetail::pluck('invoice')->all())->toBe(['INV-SHIPPED']);
});

it('reconciles against existing aria records when fetch completes', function () {
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
        'invoice_number' => 'INV-EXISTS',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => '2026-08-01',
    ]);

    $this->artisan('jubelio:get-orders')->assertSuccessful();

    $import->refresh();
    expect($import->status)->toBe(1);
    expect(Crongetorderdetail::pluck('invoice')->all())->toBe(['INV-MISSING']);
    expect(ScheduledTask::where('command', 'jubelio:get-orders')->value('is_active'))->toBeFalse();
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
    expect(Crongetorderdetail::pluck('invoice')->sort()->values()->all())->toBe(['INV-P1', 'INV-P2']);
});

it('imports remaining details into jubelio orders with source 2', function () {
    seedGetOrdersScheduledTask();

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
        'total' => 1,
        'count' => 1,
        'status' => 1,
    ]);

    Crongetorderdetail::create([
        'crongetorder_id' => $import->id,
        'jubelio_order_id' => 'so-200',
        'invoice' => 'INV-BACKFILL-200',
        'order_status' => 'SHIPPED',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.import'))
        ->assertRedirect(route('jubelio.index'));

    $order = Jubelioorder::where('invoice', 'INV-BACKFILL-200')->first();
    expect($order)->not->toBeNull();
    expect($order->source)->toBe(2);
    expect($order->payload)->toBe('{}');
    expect($order->status)->toBe(0);
    expect(Crongetorder::count())->toBe(0);
    expect(ScheduledTask::where('command', 'jubelio:get-orders')->value('is_active'))->toBeFalse();
});

it('resets an import and disables the scheduled task', function () {
    $task = seedGetOrdersScheduledTask();
    $task->update(['is_active' => true]);

    $import = Crongetorder::create([
        'from' => '2026-08-01',
        'to' => 0,
    ]);
    Crongetorderdetail::create([
        'crongetorder_id' => $import->id,
        'invoice' => 'INV-RESET',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jubelio.get-orders.reset'))
        ->assertRedirect();

    expect(Crongetorder::count())->toBe(0);
    expect(Crongetorderdetail::count())->toBe(0);
    expect($task->fresh()->is_active)->toBeFalse();
});

it('bulk reconciles via service without correlated subqueries', function () {
    $import = Crongetorder::create(['from' => '2026-08-01', 'to' => 0]);

    Crongetorderdetail::create([
        'crongetorder_id' => $import->id,
        'invoice' => 'INV-KEEP',
        'order_status' => 'SHIPPED',
        'is_canceled' => 'N',
    ]);
    Crongetorderdetail::create([
        'crongetorder_id' => $import->id,
        'invoice' => 'INV-DROP-STATUS',
        'order_status' => 'DRAFT',
        'is_canceled' => 'N',
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'jo-1',
        'source' => 1,
        'invoice' => 'INV-DROP-JO',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => '{}',
        'status' => 0,
    ]);
    Crongetorderdetail::create([
        'crongetorder_id' => $import->id,
        'invoice' => 'INV-DROP-JO',
        'order_status' => 'SHIPPED',
        'is_canceled' => 'N',
    ]);

    $removed = app(JubelioGetOrdersService::class)->reconcile($import);

    expect($removed)->toBe(2);
    expect(Crongetorderdetail::pluck('invoice')->all())->toBe(['INV-KEEP']);
});
