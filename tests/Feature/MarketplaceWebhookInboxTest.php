<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use App\Models\TikTokShopWebhookInbox;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

it('filters events by channel and date with pagination query string', function () {
    $receivedShopee = now()->subDays(2);
    $receivedTiktok = now()->subDay();
    $receivedOld = now()->subDays(30);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'ORD-S',
        'shop_ref' => '9001',
        'order_status' => 'SHIPPED',
        'last_event_at' => $receivedShopee,
        'first_received_at' => $receivedShopee,
    ]);
    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'ORD-T',
        'shop_ref' => 'tt-shop',
        'order_status' => 'UNPAID',
        'last_event_at' => $receivedTiktok,
        'first_received_at' => $receivedTiktok,
    ]);
    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'ORD-OLD',
        'shop_ref' => '9001',
        'order_status' => 'OLD',
        'last_event_at' => $receivedOld,
        'first_received_at' => $receivedOld,
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:1:3:ORD-S:1',
        'event_type' => '3',
        'shop_ref' => '9001',
        'order_ref' => 'ORD-S',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'received_at' => $receivedShopee,
    ]);

    $from = now()->subDays(7)->format('Y-m-d');
    $to = now()->format('Y-m-d');

    $this->actingAs($this->user)->get(route('marketplace.webhooks.index', [
        'channel' => 'shopee',
        'date_from' => $from,
        'date_to' => $to,
        'per_page' => 10,
        'view' => 'events',
    ]))
        ->assertRedirect(route('omnichannel.orders.index', [
            'channel' => 'shopee',
            'date_from' => $from,
            'date_to' => $to,
            'per_page' => 10,
        ]));

    $response = $this->actingAs($this->user)->get(route('omnichannel.orders.index', [
        'channel' => 'shopee',
        'date_from' => $from,
        'date_to' => $to,
        'per_page' => 10,
    ]));

    $response->assertOk()
        ->assertSee('ORD-S', false)
        ->assertDontSee('ORD-T', false)
        ->assertDontSee('ORD-OLD', false);
});

it('finds orders by partial order_ref and ignores date filter', function () {
    $receivedOld = now()->subDays(60);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'SP-SEARCH-999',
        'shop_ref' => '9001',
        'order_status' => 'SHIPPED',
        'last_event_at' => $receivedOld,
        'first_received_at' => $receivedOld,
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:1:3:SP-SEARCH-999:1',
        'event_type' => '3',
        'shop_ref' => '9001',
        'order_ref' => 'SP-SEARCH-999',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'received_at' => $receivedOld,
    ]);

    $narrowFrom = now()->subDay()->format('Y-m-d');
    $narrowTo = now()->format('Y-m-d');

    $this->actingAs($this->user)
        ->get(route('omnichannel.orders.index', [
            'order_ref' => 'SEARCH-999',
            'date_from' => $narrowFrom,
            'date_to' => $narrowTo,
        ]))
        ->assertOk()
        ->assertSee('SP-SEARCH-999', false);

    $inboxQuery = app(MarketplaceWebhookInboxQuery::class);
    $filters = $inboxQuery->parseFilters(Request::create('/', 'GET', [
        'order_ref' => 'SEARCH-999',
        'date_from' => $narrowFrom,
        'date_to' => $narrowTo,
        'view' => 'events',
    ]));

    $eventRows = $inboxQuery->eventsQuery($filters)->get();
    expect($eventRows)->toHaveCount(1);
    expect($eventRows->first()->order_ref)->toBe('SP-SEARCH-999');
});

it('preserves order_ref in legacy marketplace redirect', function () {
    $this->actingAs($this->user)
        ->get(route('marketplace.webhooks.index', [
            'channel' => 'shopee',
            'order_ref' => 'XY-42',
            'date_from' => now()->subDays(3)->format('Y-m-d'),
        ]))
        ->assertRedirect(route('omnichannel.orders.index', [
            'channel' => 'shopee',
            'order_ref' => 'XY-42',
            'date_from' => now()->subDays(3)->format('Y-m-d'),
        ]));
});

it('filters orders view by partial order status', function () {
    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'O-1',
        'shop_ref' => '77',
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now()->subDay(),
        'first_received_at' => now()->subDays(2),
        'updated_at' => now(),
    ]);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'O-2',
        'shop_ref' => '77',
        'order_status' => 'COMPLETED',
        'last_event_at' => now()->subDay(),
        'first_received_at' => now()->subDays(2),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('marketplace.webhooks.index', [
            'view' => 'orders',
            'order_status' => 'AWAITING',
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]))
        ->assertRedirect();

    $this->actingAs($this->user)
        ->get(route('omnichannel.orders.index', [
            'order_status' => 'AWAITING',
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSee('O-1', false)
        ->assertDontSee('O-2', false);
});

it('redirects legacy tiktok webhook inbox route to omnichannel orders', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.webhooks.index', ['channel' => 'tiktok']))
        ->assertRedirect(route('omnichannel.orders.index', ['channel' => 'tiktok']));
});

it('redirects marketplace webhooks route to omnichannel orders', function () {
    $this->actingAs($this->user)
        ->get(route('marketplace.webhooks.index', ['channel' => 'shopee', 'view' => 'events']))
        ->assertRedirect(route('omnichannel.orders.index', ['channel' => 'shopee']));
});

it('backfills legacy inbox tables idempotently', function () {
    ShopeePushInbox::query()->create([
        'shop_id' => 42,
        'push_code' => 3,
        'ordersn' => 'SP-BF-1',
        'order_status' => 'READY',
        'signature_ok' => true,
        'callback_url' => 'https://example.test/shopee/push',
        'payload' => ['timestamp' => 1700000000, 'data' => ['ordersn' => 'SP-BF-1']],
        'created_at' => now()->subDays(3),
    ]);

    TikTokShopWebhookInbox::query()->create([
        'tts_notification_id' => 'bf-tts-1',
        'event_type' => 1,
        'shop_id' => 'shop-x',
        'order_id' => 'TT-BF-1',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => ['type' => 1, 'data' => ['order_id' => 'TT-BF-1']],
        'created_at' => now()->subDays(2),
    ]);

    Artisan::call('marketplace:backfill-webhook-events');
    expect(MarketplaceWebhookEvent::count())->toBe(2);

    Artisan::call('marketplace:backfill-webhook-events');
    expect(MarketplaceWebhookEvent::count())->toBe(2);

    Artisan::call('marketplace:backfill-webhook-events', ['--dry-run' => true]);
    expect(MarketplaceWebhookEvent::count())->toBe(2);
});

it('denies webhook legacy route without marketplace tiktok or omnichannel permission', function () {
    $limited = User::factory()->create();
    expect($limited->is_superadmin)->toBeFalse();

    $this->actingAs($limited)
        ->get(route('marketplace.webhooks.index'))
        ->assertForbidden();
});