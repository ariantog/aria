<?php

use App\Models\MarketplaceOrderShadow;
use App\Models\OmnichannelPostSellStat;
use App\Models\User;
use App\Services\Marketplace\MarketplaceChannelOrderOpsBucket;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\OmnichannelCompareDashboardStats;
use App\Services\Marketplace\OmnichannelDashboardCacheBuster;
use App\Services\Marketplace\OmnichannelOpsBucketStatsCache;
use App\Services\Marketplace\OmnichannelPostSellDashboardStats;
use App\Services\Marketplace\OmnichannelPostSellStatsQuery;
use App\Services\Marketplace\OmnichannelPostSellStatsStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'marketplace.post_sell.enabled' => true,
        'marketplace.post_sell.dry_run' => false,
        'marketplace.post_sell.gate' => 'shipped',
        'marketplace.post_sell.max_age_days' => 30,
        'marketplace.post_sell.channels' => ['shopee'],
        'marketplace.dashboard_cache.ops_bucket_ttl_seconds' => 300,
        'marketplace.dashboard_cache.compare_stats_ttl_seconds' => 300,
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

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'CACHE-ELIGIBLE',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 5,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $store = app(OmnichannelPostSellStatsStore::class);
    $store->rebuildFull();

    $stats = app(OmnichannelPostSellDashboardStats::class)->counts();

    expect($stats['posted'])->toBe(1)
        ->and($stats['eligible_to_post'])->toBe(1)
        ->and($stats['stats_updated_at'])->not->toBeNull();

    $row = OmnichannelPostSellStat::query()->find(OmnichannelPostSellStat::ROW_ID);
    expect((int) $row->posted)->toBe(1)
        ->and((int) $row->eligible_to_post)->toBe(1);
});

it('increments post sell snapshot when a new in scope order is upserted', function () {
    app(OmnichannelPostSellStatsStore::class)->rebuildFull();

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'CACHE-INCR',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 7,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $stats = app(OmnichannelPostSellDashboardStats::class)->counts();
    expect($stats['eligible_to_post'])->toBe(1);
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

it('caches ops bucket counts per filter fingerprint', function () {
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'OPS-CACHE-1',
        'order_status' => 'PROCESSED',
        'shop_ref' => 'shop-1',
        'last_event_at' => now(),
        'first_received_at' => now()->subDay(),
    ]);

    $inbox = app(MarketplaceWebhookInboxQuery::class);
    $cache = app(OmnichannelOpsBucketStatsCache::class);
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
    $fp = $cache->fingerprint($filters);
    $version = app(OmnichannelDashboardCacheBuster::class)->opsBucketVersion();
    $cacheKey = "omnichannel.ops_buckets.v{$version}.{$fp}";

    expect(Cache::has($cacheKey))->toBeFalse();

    $first = $cache->countBuckets(clone $base, $filters);
    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($first[MarketplaceChannelOrderOpsBucket::BUCKET_ALL] ?? 0)->toBeGreaterThan(0);

    $second = $cache->countBuckets(clone $base, $filters);
    expect($second)->toBe($first);
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

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'REG-ERR',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 0,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $scan = app(OmnichannelPostSellStatsQuery::class)->computeCountsFromDatabase();
    expect($scan['posted'])->toBe(1)
        ->and($scan['errors'])->toBe(1);
});