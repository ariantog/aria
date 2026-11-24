<?php

namespace App\Services\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Ops queue buckets for marketplace_channel_orders (Current orders view).
 *
 * Unprocessed = paid-valid rows that still need AWB/pick (Shopee PROCESSED; TikTok AWAITING_SHIPMENT).
 * Ready to ship = label printed / handover-ready (Shopee READY_TO_SHIP + RETRY_SHIP; TikTok AWAITING_COLLECTION only).
 */
class MarketplaceChannelOrderOpsBucket
{
    public const BUCKET_ALL = 'all';

    public const BUCKET_UNPROCESSED = 'unprocessed';

    public const BUCKET_READY_TO_SHIP = 'ready_to_ship';

    public const BUCKET_IN_TRANSIT = 'in_transit';

    public const BUCKET_COMPLETED = 'completed';

    public const BUCKET_CANCELLED = 'cancelled';

    public const DEFAULT_ORDERS_VIEW = self::BUCKET_UNPROCESSED;

    /** @var list<string> */
    public const ALLOWED = [
        self::BUCKET_ALL,
        self::BUCKET_UNPROCESSED,
        self::BUCKET_READY_TO_SHIP,
        self::BUCKET_IN_TRANSIT,
        self::BUCKET_COMPLETED,
        self::BUCKET_CANCELLED,
    ];

    /** @var array<string, list<string>> */
    private const SHOPEE_STATUSES = [
        self::BUCKET_UNPROCESSED => ['PROCESSED'],
        self::BUCKET_READY_TO_SHIP => ['READY_TO_SHIP', 'RETRY_SHIP'],
        self::BUCKET_IN_TRANSIT => ['SHIPPED', 'TO_CONFIRM_RECEIVE'],
        self::BUCKET_COMPLETED => ['COMPLETED'],
        self::BUCKET_CANCELLED => ['IN_CANCEL', 'CANCELLED', 'CANCELED'],
    ];

    /** @var array<string, list<string>> */
    private const TIKTOK_STATUSES = [
        self::BUCKET_UNPROCESSED => ['AWAITING_SHIPMENT'],
        self::BUCKET_READY_TO_SHIP => ['AWAITING_COLLECTION'],
        self::BUCKET_IN_TRANSIT => ['PARTIALLY_SHIPPING', 'IN_TRANSIT', 'DELIVERED'],
        self::BUCKET_COMPLETED => ['COMPLETED'],
        self::BUCKET_CANCELLED => ['CANCEL', 'CANCELLED', 'CANCELED'],
    ];

  /** Tokopedia rows share TikTok vocabulary (commerce_platform=TOKOPEDIA). */
    private const TOKOPEDIA_STATUSES = self::TIKTOK_STATUSES;

    public static function normalize(?string $value, string $view): ?string
    {
        if ($view !== 'orders') {
            return null;
        }

        $bucket = strtolower(trim((string) $value));
        if ($bucket === '') {
            return self::DEFAULT_ORDERS_VIEW;
        }

        return in_array($bucket, self::ALLOWED, true) ? $bucket : self::DEFAULT_ORDERS_VIEW;
    }

    /**
     * @return array<string, int>
     */
    public function countBuckets(Builder $baseQuery): array
    {
        $counts = [
            self::BUCKET_ALL => (clone $baseQuery)->count(),
        ];

        foreach (self::ALLOWED as $bucket) {
            if ($bucket === self::BUCKET_ALL) {
                continue;
            }

            $scoped = clone $baseQuery;
            $this->applyOpsBucketFilter($scoped, $bucket);
            $counts[$bucket] = $scoped->count();
        }

        return $counts;
    }

    /**
     * @param  Builder<\App\Models\MarketplaceChannelOrder>  $query
     */
    public function applyOpsBucketFilter(Builder $query, string $opsBucket, string $channelFilter = 'all'): void
    {
        if ($opsBucket === self::BUCKET_ALL) {
            return;
        }

        $query->where(function (Builder $outer) use ($opsBucket, $channelFilter): void {
            $first = true;
            foreach ($this->scopesForChannelFilter($channelFilter) as $scope) {
                $boolean = $first ? 'where' : 'orWhere';
                $first = false;
                $this->appendScopeClause(
                    $outer,
                    $scope['channel'],
                    $scope['platform'],
                    $opsBucket,
                    $boolean,
                );
            }
            if ($first) {
                $outer->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @return list<array{channel: string, platform: string|null}>
     */
    private function scopesForChannelFilter(string $channelFilter): array
    {
        return match ($channelFilter) {
            'shopee' => [['channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE, 'platform' => null]],
            'tiktok' => [['channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK, 'platform' => 'tiktok']],
            'tokopedia' => [['channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK, 'platform' => 'tokopedia']],
            default => [
                ['channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE, 'platform' => null],
                ['channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK, 'platform' => 'tiktok'],
                ['channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK, 'platform' => 'tokopedia'],
            ],
        };
    }

    /**
     * @param  Builder<\App\Models\MarketplaceChannelOrder>  $query
     */
    public function applyAllBucketSort(Builder $query): void
    {
        $priorityCase = $this->unprocessedPriorityCaseSql();
        $query->getQuery()->orders = [];
        $query->orderBy(new Expression($priorityCase))
            ->orderByDesc('last_event_at')
            ->orderByDesc('id');
    }

    public function cardTitle(string $bucket): string
    {
        return match ($bucket) {
            self::BUCKET_ALL => 'All',
            self::BUCKET_UNPROCESSED => 'Unprocessed',
            self::BUCKET_READY_TO_SHIP => 'Ready to ship',
            self::BUCKET_IN_TRANSIT => 'In transit',
            self::BUCKET_COMPLETED => 'Completed',
            self::BUCKET_CANCELLED => 'Cancelled',
            default => $bucket,
        };
    }

    /**
     * @return list<string>
     */
    public function statusesForBucket(string $scope, string $bucket): array
    {
        $map = match ($scope) {
            'shopee' => self::SHOPEE_STATUSES,
            'tokopedia' => self::TOKOPEDIA_STATUSES,
            default => self::TIKTOK_STATUSES,
        };

        return $map[$bucket] ?? [];
    }

    private function unprocessedPriorityCaseSql(): string
    {
        $parts = [];
        foreach ($this->statusesForBucket('shopee', self::BUCKET_UNPROCESSED) as $status) {
            $parts[] = "(channel = 'shopee' AND UPPER(TRIM(order_status)) = '".addslashes($status)."')";
        }
        foreach ($this->statusesForBucket('tiktok', self::BUCKET_UNPROCESSED) as $status) {
            $esc = addslashes($status);
            $parts[] = "(channel = 'tiktok' AND UPPER(TRIM(order_status)) = '{$esc}' AND (commerce_platform IS NULL OR commerce_platform = '' OR UPPER(commerce_platform) != 'TOKOPEDIA'))";
            $parts[] = "(channel = 'tiktok' AND UPPER(TRIM(order_status)) = '{$esc}' AND UPPER(COALESCE(commerce_platform, '')) = 'TOKOPEDIA')";
        }

        if ($parts === []) {
            return '0';
        }

        return 'CASE WHEN '.implode(' OR ', $parts).' THEN 0 ELSE 1 END';
    }

    /**
     * @param  Builder<\App\Models\MarketplaceChannelOrder>  $outer
     */
    private function appendScopeClause(
        Builder $outer,
        string $channel,
        ?string $platformScope,
        string $opsBucket,
        string $boolean,
    ): void {
        $statusKey = match ($platformScope) {
            'tokopedia' => 'tokopedia',
            'tiktok' => 'tiktok',
            default => $channel,
        };
        $statuses = $this->statusesForBucket($statusKey, $opsBucket);
        if ($statuses === []) {
            return;
        }

        $upperStatuses = array_map(static fn (string $s) => strtoupper($s), $statuses);

        $outer->{$boolean}(function (Builder $inner) use ($channel, $platformScope, $upperStatuses): void {
            $inner->where('channel', $channel);
            $this->applyCommercePlatformScope($inner, $platformScope);
            $inner->whereIn(DB::raw('UPPER(TRIM(order_status))'), $upperStatuses);
        });
    }

    /**
     * @param  Builder<\App\Models\MarketplaceChannelOrder>  $query
     */
    private function applyCommercePlatformScope(Builder $query, ?string $scope): void
    {
        if ($scope === 'tokopedia') {
            $query->whereRaw('UPPER(COALESCE(commerce_platform, "")) = ?', ['TOKOPEDIA']);

            return;
        }

        if ($scope === 'tiktok') {
            $query->where(function (Builder $inner): void {
                $inner->whereNull('commerce_platform')
                    ->orWhere('commerce_platform', '')
                    ->orWhereRaw('UPPER(commerce_platform) != ?', ['TOKOPEDIA']);
            });
        }
    }
}