<?php

use App\Models\MarketplaceOrderShadow;
use App\Models\OmnichannelPostSellStat;
use App\Models\User;
use App\Services\Marketplace\MarketplaceChannelOrderOpsBucket;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\OmnichannelCompareDashboardStats;
use App\Services\Marketplace\OmnichannelDashboardCacheBuster;
use App\Services\Marketplace\OmnichannelPostSellDashboardStats;
use App\Services\Marketplace\OmnichannelPostSellStatsQuery;
use App\Services\Marketplace\OmnichannelPostSellStatsStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

beforeEach(function () {
    config([
        'marketplace.post_sell.enabled' => true,
        'marketplace.post_sell.dry_run' => false,
        'marketplace.post_sell.gate' => 'shipped',
        'marketplace.post_sell.max_age_days' => 30,
        'marketplace.post_sell.channels' => ['shopee'],
        'marketplace.dashboard_cache.compare_stats_ttl_seconds' => 300,
        'marketplace.dashboard_cache.post_sell_fresh_seconds' => 60,
    ]);

    if (! Schema::hasTable('omnichannel_post_sell_stats')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_11_28_210000_create_omnichannel_post_sell_stats_table.php',
            '--force' => true,
        ]);
    }

    OmnichannelPostSellStat::query()->where('id', OmnichannelPostSellStat::ROW_ID)->delete();
    Cache::flush();
});

it('rebuilds post sell snapshot and dashboard reads cached row', function () {
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'CACHE-POSTED',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 1,
        'posted_transaction_id' => 99,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $store = app(OmnichannelPostSellStatsStore::class);
    $store->rebuildFull();

    $stats = app(OmnichannelPostSellDashboardStats::class)->counts();

    expect($stats['posted'])->toBe(1)
        ->and($stats['stats_updated_at'])->not->toBeNull()
        ->and($stats['stats_stale'])->toBeFalse();
});

it('does not full scan post sell stats on stale snapshot read', function () {
    OmnichannelPostSellStat::query()->insert([
        'id' => OmnichannelPostSellStat::ROW_ID,
        'scope_key' => app(\App\Services\Marketplace\OmnichannelPostSellStatsScope::class)->scopeKey(),
        'posted' => 3,
        'eligible_to_post' => 2,
        'errors' => 1,
        'updated_at' => now()->subMinutes(10),
    ]);

    $this->mock(OmnichannelPostSellStatsQuery::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('computeCountsFromDatabase');
    });

    $stats = app(OmnichannelPostSellStatsStore::class)->countsForDashboard();

    expect($stats['posted'])->toBe(3)
        ->and($stats['eligible_to_post'])->toBe(2)
        ->and($stats['stats_stale'])->toBeTrue();
});

it('refreshes post sell stats via artisan reconcile command', function () {
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'CMD-ERR',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 0,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    Artisan::call('omnichannel:refresh-post-sell-stats');

    $stats = app(OmnichannelPostSellDashboardStats::class)->counts();
    expect($stats['errors'])->toBe(1);
});

it('aggregates ops bucket counts in one query matching legacy counters', function () {
    $now = now();
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'AGG-UNPROC',
        'order_status' => 'PROCESSED',
        'last_event_at' => $now,
        'first_received_at' => $now,
    ]);
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'AGG-SHIP',
        'order_status' => 'SHIPPED',
        'last_event_at' => $now,
        'first_received_at' => $now,
    ]);

    $inbox = app(MarketplaceWebhookInboxQuery::class);
    $ops = app(MarketplaceChannelOrderOpsBucket::class);
    $filters = [
        'channel' => 'all',
        'date_from' => Carbon::now()->subDays(7)->startOfDay(),
        'date_to' => Carbon::now()->endOfDay(),
        'warehouse_id' => null,
        'shop_ref' => null,
        'order_ref' => null,
        'order_status' => null,
        'view' => 'orders',
        'ops_bucket' => 'all',
        'per_page' => 25,
    ];

    $base = $inbox->ordersQueryForStats($filters);
    $aggregate = $ops->aggregateBucketCounts(clone $base, 'all');
    unset($aggregate['native_posted']);
    $legacy = $ops->countBuckets(clone $base);

    expect($aggregate)->toBe($legacy);
});

it('caches shadow compare stats until busted', function () {
    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 1,
        'external_order_sn' => 'CMP-CACHE-1',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_SKIPPED,
        'last_event_at' => now(),
    ]);

    $service = app(OmnichannelCompareDashboardStats::class);
    $first = $service->counts();
    expect($first['matched'])->toBeGreaterThanOrEqual(1);

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 1,
        'external_order_sn' => 'CMP-CACHE-2',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MISMATCH,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_SKIPPED,
        'last_event_at' => now(),
    ]);

    $stillCached = $service->counts();
    expect($stillCached['matched'])->toBe($first['matched']);

    app(OmnichannelDashboardCacheBuster::class)->bustCompareStats();
    $fresh = $service->counts();
    expect($fresh['matched'])->toBe($first['matched'])
        ->and($fresh['mismatch'])->toBeGreaterThan($stillCached['mismatch'] ?? 0);
});

it('orders index does not render full post sell kpi grid', function () {
    $user = User::factory()->create();
    app(\App\Services\PermissionGenerator::class)->generateForModule('Omnichannel');
    $this->actingAs($user)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertDontSee('data-testid="omnichannel-post-sell-eligible-count"', false)
        ->assertSee('data-testid="omnichannel-ops-bucket-cards"', false);
});

it('full scan query still matches eligibility for dashboard regression', function () {
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'REG-POSTED',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 1,
        'posted_transaction_id' => 1,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $scan = app(OmnichannelPostSellStatsQuery::class)->computeCountsFromDatabase();
    expect($scan['posted'])->toBe(1);
});