<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\PermissionGenerator;
use Illuminate\Http\Request;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

it('parses shopee order_sn YYMMDD prefix and prefers pay_time in payload', function () {
    $resolver = app(\App\Services\Marketplace\MarketplaceChannelOrderOrderedAtResolver::class);

    $parsed = $resolver->parseShopeeOrderSnDate('261116DGYPF12V');
    expect($parsed)->not->toBeNull()
        ->and($parsed->format('Y-m-d'))->toBe('2026-11-16');

    $payTime = Carbon\Carbon::parse('2026-11-17 14:30:00', config('app.timezone'))->getTimestamp();
    $resolved = $resolver->resolveFromWebhookPayload(
        MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        '261116DGYPF12V',
        [
            'data' => [
                'pay_time' => $payTime,
                'payment_method' => 'Credit Card',
            ],
        ],
        'PROCESSED',
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved->getTimestamp())->toBe($payTime);
});

it('filters current orders by marketplace_ordered_at not last_event_at', function () {
    $orderDate = Carbon\Carbon::parse('2026-11-16 08:00:00');
    $lastEvent = Carbon\Carbon::parse('2026-11-26 18:00:00');

    MarketplaceChannelOrder::query()->create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => '261116DGYPF12V',
        'shop_ref' => '9001',
        'order_status' => 'PROCESSED',
        'marketplace_ordered_at' => $orderDate,
        'last_event_at' => $lastEvent,
        'first_received_at' => $lastEvent,
    ]);

    MarketplaceChannelOrder::query()->create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => '261124INSIDE',
        'shop_ref' => '9001',
        'order_status' => 'PROCESSED',
        'marketplace_ordered_at' => Carbon\Carbon::parse('2026-11-24 10:00:00'),
        'last_event_at' => Carbon\Carbon::parse('2026-11-24 11:00:00'),
        'first_received_at' => Carbon\Carbon::parse('2026-11-24 11:00:00'),
    ]);

    $from = '2026-11-20';
    $to = '2026-11-27';

    $this->actingAs($this->user)
        ->get(route('omnichannel.orders.index', [
            'view' => 'orders',
            'ops_bucket' => 'all',
            'date_from' => $from,
            'date_to' => $to,
        ]))
        ->assertOk()
        ->assertDontSee('261116DGYPF12V', false)
        ->assertSee('261124INSIDE', false);

    $inbox = app(MarketplaceWebhookInboxQuery::class);
    $filters = $inbox->parseFilters(Request::create('/', 'GET', [
        'view' => 'orders',
        'ops_bucket' => 'all',
        'date_from' => $from,
        'date_to' => $to,
    ]));

    $refs = $inbox->ordersQuery($filters)->pluck('order_ref')->all();
    expect($refs)->toContain('261124INSIDE')
        ->and($refs)->not->toContain('261116DGYPF12V');
});

it('persists marketplace_ordered_at when ingesting shopee webhook', function () {
    config(['marketplace.webhook.unified_storage' => true]);

    $recorder = app(\App\Services\Marketplace\MarketplaceWebhookRecorder::class);
    $payTime = Carbon\Carbon::parse('2026-11-17 12:00:00', config('app.timezone'))->getTimestamp();

    $recorder->recordShopee(
        [
            'data' => [
                'ordersn' => '261116DGYPF12V',
                'status' => 'PROCESSED',
                'pay_time' => $payTime,
                'update_time' => Carbon\Carbon::parse('2026-11-26 12:00:00')->getTimestamp(),
            ],
            'shop_id' => 727720655,
            'code' => 3,
            'timestamp' => Carbon\Carbon::parse('2026-11-26 12:00:00')->getTimestamp(),
        ],
        true,
        727720655,
        3,
        '261116DGYPF12V',
        'PROCESSED',
    );

    $order = MarketplaceChannelOrder::query()
        ->where('order_ref', '261116DGYPF12V')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->marketplace_ordered_at?->getTimestamp())->toBe($payTime);
});