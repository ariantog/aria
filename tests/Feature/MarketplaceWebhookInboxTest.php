<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use App\Models\TikTokShopWebhookInbox;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

it('filters events by channel and date with pagination query string', function () {
    MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:1:3:ORD-S:1',
        'event_type' => '3',
        'shop_ref' => '9001',
        'order_ref' => 'ORD-S',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'received_at' => now()->subDays(2),
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:filter-1',
        'event_type' => '1',
        'shop_ref' => 'tt-shop',
        'order_ref' => 'ORD-T',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => ['type' => 1],
        'received_at' => now()->subDay(),
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:1:3:ORD-OLD:1',
        'event_type' => '3',
        'shop_ref' => '9001',
        'order_ref' => 'ORD-OLD',
        'order_status' => 'OLD',
        'signature_ok' => true,
        'payload' => [],
        'received_at' => now()->subDays(30),
    ]);

    $from = now()->subDays(7)->format('Y-m-d');
    $to = now()->format('Y-m-d');

    $response = $this->actingAs($this->user)->get(route('marketplace.webhooks.index', [
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
        ->assertOk()
        ->assertSee('O-1', false)
        ->assertDontSee('O-2', false);
});

it('redirects legacy tiktok webhook inbox route to unified inbox', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.webhooks.index', ['channel' => 'tiktok']))
        ->assertRedirect(route('marketplace.webhooks.index', ['channel' => 'tiktok']));
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

it('denies inbox without marketplace tiktok or omnichannel permission', function () {
    $limited = User::factory()->create();
    expect($limited->is_superadmin)->toBeFalse();

    $this->actingAs($limited)
        ->get(route('marketplace.webhooks.index'))
        ->assertForbidden();
});