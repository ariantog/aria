<?php

namespace App\Services\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class OmnichannelOpsBucketStatsCache
{
    public function __construct(
        private MarketplaceChannelOrderOpsBucket $opsBucket,
        private OmnichannelDashboardCacheBuster $buster,
    ) {}

    /**
     * @param  Builder<\App\Models\MarketplaceChannelOrder>  $baseQuery
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function countBuckets(Builder $baseQuery, array $filters): array
    {
        $ttl = max(30, (int) config('marketplace.dashboard_cache.ops_bucket_ttl_seconds', 90));
        $fingerprint = $this->fingerprint($filters);
        $version = $this->buster->opsBucketVersion();
        $cacheKey = "omnichannel.ops_buckets.v{$version}.{$fingerprint}";

        return Cache::remember($cacheKey, $ttl, function () use ($baseQuery) {
            return $this->opsBucket->countBuckets($baseQuery);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function fingerprint(array $filters): string
    {
        $payload = [
            'channel' => (string) ($filters['channel'] ?? 'all'),
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
            'warehouse_id' => (string) ($filters['warehouse_id'] ?? ''),
            'shop_ref' => (string) ($filters['shop_ref'] ?? ''),
            'order_ref' => (string) ($filters['order_ref'] ?? ''),
        ];

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}