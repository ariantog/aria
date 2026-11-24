<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\User;
use App\Services\Marketplace\MarketplaceChannelOrderOpsBucket;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\PermissionGenerator;
use Illuminate\Http\Request;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
    $this->admin = User::factory()->create();
});

function seedOpsBucketFixtureOrders(): void
{
  $now = now();
    $rows = [
        ['channel' => 'shopee', 'order_ref' => 'OPS-UNPROC-S', 'order_status' => 'PROCESSED', 'last' => $now->copy()->subMinutes(1)],
        ['channel' => 'shopee', 'order_ref' => 'OPS-READY-S', 'order_status' => 'READY_TO_SHIP', 'last' => $now, 'awb_printed_at' => $now],
        ['channel' => 'shopee', 'order_ref' => 'OPS-READY-NOAWB', 'order_status' => 'READY_TO_SHIP', 'last' => $now->copy()->subMinutes(3)],
        ['channel' => 'shopee', 'order_ref' => 'OPS-SHIP-S', 'order_status' => 'SHIPPED', 'last' => $now],
        ['channel' => 'tiktok', 'order_ref' => 'OPS-UNPROC-T', 'order_status' => 'AWAITING_SHIPMENT', 'last' => $now->copy()->subMinutes(2)],
        ['channel' => 'tiktok', 'order_ref' => 'OPS-READY-T', 'order_status' => 'AWAITING_COLLECTION', 'last' => $now],
        ['channel' => 'tiktok', 'commerce_platform' => 'TOKOPEDIA', 'order_ref' => 'OPS-DONE-TK', 'order_status' => 'COMPLETED', 'last' => $now],
        ['channel' => 'shopee', 'order_ref' => 'OPS-CANCEL-S', 'order_status' => 'CANCELLED', 'last' => $now],
    ];

    foreach ($rows as $row) {
        MarketplaceChannelOrder::query()->create([
            'channel' => $row['channel'],
            'commerce_platform' => $row['commerce_platform'] ?? null,
            'order_ref' => $row['order_ref'],
            'shop_ref' => 'shop-1',
            'order_status' => $row['order_status'],
            'last_event_at' => $row['last'],
            'first_received_at' => $now->subDay(),
            'awb_printed_at' => $row['awb_printed_at'] ?? null,
        ]);
    }
}

it('defaults current orders view to unprocessed ops bucket', function () {
    seedOpsBucketFixtureOrders();

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-ops-bucket-cards"', false)
        ->assertSee('OPS-UNPROC-S', false)
        ->assertSee('OPS-UNPROC-T', false)
        ->assertSee('OPS-READY-NOAWB', false)
        ->assertDontSee('OPS-READY-S', false)
        ->assertDontSee('OPS-SHIP-S', false);
});

it('filters current orders by ops bucket query param', function () {
    seedOpsBucketFixtureOrders();

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', [
            'view' => 'orders',
            'ops_bucket' => 'ready_to_ship',
        ]))
        ->assertOk()
        ->assertSee('OPS-READY-S', false)
        ->assertSee('OPS-READY-T', false)
        ->assertDontSee('OPS-UNPROC-S', false);
});

it('sorts all bucket with unprocessed rows first', function () {
    seedOpsBucketFixtureOrders();

    $inbox = app(MarketplaceWebhookInboxQuery::class);
    $filters = $inbox->parseFilters(Request::create('/', 'GET', [
        'view' => 'orders',
        'ops_bucket' => 'all',
        'date_from' => now()->subDays(30)->format('Y-m-d'),
        'date_to' => now()->format('Y-m-d'),
    ]));

    $refs = $inbox->ordersQuery($filters)->pluck('order_ref')->all();

    $unprocessedIndex = min(
        array_search('OPS-UNPROC-S', $refs, true) ?: PHP_INT_MAX,
        array_search('OPS-UNPROC-T', $refs, true) ?: PHP_INT_MAX,
    );
    $readyIndex = array_search('OPS-READY-S', $refs, true);
    expect($unprocessedIndex)->toBeLessThan($readyIndex);
});

it('does not apply ops bucket on events view', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'EVENTS-ONLY',
        'shop_ref' => '1',
        'order_status' => 'SHIPPED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $inbox = app(MarketplaceWebhookInboxQuery::class);
    $filters = $inbox->parseFilters(Request::create('/', 'GET', ['view' => 'events']));

    expect($filters['ops_bucket'])->toBeNull();
});

it('exposes stat card counts per ops bucket', function () {
    seedOpsBucketFixtureOrders();

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', [
            'view' => 'orders',
            'ops_bucket' => 'all',
        ]))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-ops-bucket-unprocessed"', false)
        ->assertSee('>3<', false);
});

it('keeps shopee ready_to_ship without awb in unprocessed bucket', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'WEBHOOK-READY',
        'shop_ref' => 'shop-1',
        'order_status' => 'READY_TO_SHIP',
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'ops_bucket' => 'unprocessed']))
        ->assertOk()
        ->assertSee('WEBHOOK-READY', false);

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'ops_bucket' => 'ready_to_ship']))
        ->assertOk()
        ->assertDontSee('WEBHOOK-READY', false);
});

it('places shopee ready_to_ship with awb in ready_to_ship bucket', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'AWB-READY',
        'shop_ref' => 'shop-1',
        'order_status' => 'READY_TO_SHIP',
        'awb_printed_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders', 'ops_bucket' => 'ready_to_ship']))
        ->assertOk()
        ->assertSee('AWB-READY', false);
});

it('validates ops bucket via presenter service', function () {
    $service = app(MarketplaceChannelOrderOpsBucket::class);
    expect($service->statusesForBucket('shopee', 'unprocessed'))->toBe(['PROCESSED']);
    expect($service->statusesForBucket('tiktok', 'ready_to_ship'))->toBe(['AWAITING_COLLECTION']);
});

it('reverse-maps platform status to ops bucket for list chips', function () {
    $service = app(MarketplaceChannelOrderOpsBucket::class);
    expect($service->opsBucketForOrderStatus('shopee', null, 'PROCESSED'))
        ->toBe(MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED);
    expect($service->opsBucketForOrderStatus('shopee', null, 'READY_TO_SHIP', false))
        ->toBe(MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED);
    expect($service->opsBucketForOrderStatus('shopee', null, 'READY_TO_SHIP', true))
        ->toBe(MarketplaceChannelOrderOpsBucket::BUCKET_READY_TO_SHIP);
    expect($service->opsBucketForOrderStatus('tiktok', null, 'AWAITING_SHIPMENT'))
        ->toBe(MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED);
    expect($service->opsBucketForOrderStatus('tiktok', 'TOKOPEDIA', 'AWAITING_COLLECTION'))
        ->toBe(MarketplaceChannelOrderOpsBucket::BUCKET_READY_TO_SHIP);
});