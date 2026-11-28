<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;

class OmnichannelDashboardCacheBuster
{
    public const OPS_BUCKET_VERSION_KEY = 'omnichannel.dashboard.ops_bucket_version';

    public const COMPARE_STATS_CACHE_KEY = 'omnichannel.dashboard.compare_stats';

    public const JUBELIO_PENDING_CACHE_KEY = 'omnichannel.dashboard.jubelio_pending_sell';

    public function bumpOpsBucketVersion(): void
    {
        if (Cache::has(self::OPS_BUCKET_VERSION_KEY)) {
            Cache::increment(self::OPS_BUCKET_VERSION_KEY);
        } else {
            Cache::forever(self::OPS_BUCKET_VERSION_KEY, 1);
        }
    }

    public function opsBucketVersion(): int
    {
        return (int) Cache::get(self::OPS_BUCKET_VERSION_KEY, 0);
    }

    public function bustCompareStats(): void
    {
        Cache::forget(self::COMPARE_STATS_CACHE_KEY);
        Cache::forget(self::JUBELIO_PENDING_CACHE_KEY);
    }
}