<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\OmnichannelPostSellStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cached native post-sell KPI snapshot (single row) with incremental updates on order upserts.
 */
class OmnichannelPostSellStatsStore
{
    public function __construct(
        private OmnichannelPostSellEligibility $eligibility,
        private OmnichannelPostSellStatsScope $scope,
        private OmnichannelPostSellStatsClassifier $classifier,
        private OmnichannelPostSellStatsQuery $statsQuery,
    ) {}

    /**
     * @return array{
     *     posted: int,
     *     eligible_to_post: int,
     *     errors: int,
     *     gate: string,
     *     post_sells_enabled: bool,
     *     post_sell_dry_run: bool,
     *     stats_updated_at: ?string,
     *     stats_stale: bool,
     * }
     */
    public function countsForDashboard(): array
    {
        $meta = [
            'gate' => $this->eligibility->gateMode(),
            'post_sells_enabled' => $this->eligibility->masterEnabled(),
            'post_sell_dry_run' => $this->eligibility->configDryRun(),
        ];

        if (! Schema::hasTable('omnichannel_post_sell_stats')
            || ! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')) {
            return array_merge($this->emptyCounts(), $meta, [
                'stats_updated_at' => null,
                'stats_stale' => false,
            ]);
        }

        $row = OmnichannelPostSellStat::query()->find(OmnichannelPostSellStat::ROW_ID);
        $scopeKey = $this->scope->scopeKey();

        if ($row === null || $row->scope_key !== $scopeKey) {
            $row = $this->rebuildFull();
        } elseif ($this->snapshotIsStale($row)) {
            $row = $this->tryInlineRefresh($row);
        }

        return array_merge([
            'posted' => (int) $row->posted,
            'eligible_to_post' => (int) $row->eligible_to_post,
            'errors' => (int) $row->errors,
            'stats_updated_at' => $row->updated_at?->toIso8601String(),
            'stats_stale' => $this->snapshotIsStale($row),
        ], $meta);
    }

    public function rebuildFull(): OmnichannelPostSellStat
    {
        $fresh = $this->statsQuery->computeCountsFromDatabase();
        $scopeKey = $this->scope->scopeKey();

        OmnichannelPostSellStat::query()->updateOrInsert(
            ['id' => OmnichannelPostSellStat::ROW_ID],
            [
                'scope_key' => $scopeKey,
                'posted' => max(0, (int) $fresh['posted']),
                'eligible_to_post' => max(0, (int) $fresh['eligible_to_post']),
                'errors' => max(0, (int) $fresh['errors']),
                'updated_at' => now(),
            ],
        );

        return OmnichannelPostSellStat::query()->findOrFail(OmnichannelPostSellStat::ROW_ID);
    }

    /**
     * Adjust snapshot counters after a channel order create/update/delete.
     *
     * @param  array<string, mixed>|null  $originalAttributes  attributes before save (getOriginal()), null when created
     */
    public function recordOrderChange(MarketplaceChannelOrder $order, ?array $originalAttributes = null): void
    {
        if (! Schema::hasTable('omnichannel_post_sell_stats')) {
            return;
        }

        $scopeKey = $this->scope->scopeKey();
        $before = $originalAttributes !== null
            ? $this->classifier->classifyAttributes($originalAttributes)
            : null;
        $after = $this->classifier->classify($order);

        if ($before === $after) {
            return;
        }

        DB::transaction(function () use ($scopeKey, $before, $after): void {
            $row = OmnichannelPostSellStat::query()->lockForUpdate()->find(OmnichannelPostSellStat::ROW_ID);
            if ($row === null || $row->scope_key !== $scopeKey) {
                $this->rebuildFull();

                return;
            }

            foreach ([$before, $after] as $index => $bucket) {
                if ($bucket === null) {
                    continue;
                }
                $delta = $index === 0 ? -1 : 1;
                $column = $this->columnForBucket($bucket);
                $next = max(0, (int) $row->{$column} + $delta);
                $row->{$column} = $next;
            }

            $row->updated_at = now();
            $row->save();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordOrderRemoved(array $attributes): void
    {
        if (! Schema::hasTable('omnichannel_post_sell_stats')) {
            return;
        }

        $before = $this->classifier->classifyAttributes($attributes);
        if ($before === null) {
            return;
        }

        DB::transaction(function () use ($before): void {
            $scopeKey = $this->scope->scopeKey();
            $row = OmnichannelPostSellStat::query()->lockForUpdate()->find(OmnichannelPostSellStat::ROW_ID);
            if ($row === null || $row->scope_key !== $scopeKey) {
                $this->rebuildFull();

                return;
            }

            $column = $this->columnForBucket($before);
            $row->{$column} = max(0, (int) $row->{$column} - 1);
            $row->updated_at = now();
            $row->save();
        });
    }

    private function tryInlineRefresh(OmnichannelPostSellStat $row): OmnichannelPostSellStat
    {
        $timeout = max(1, (int) config('marketplace.dashboard_cache.post_sell_inline_refresh_seconds', 8));
        $previous = ini_get('max_execution_time');
        if ($previous !== false && (int) $previous > 0 && (int) $previous < $timeout + 2) {
            @set_time_limit($timeout + 2);
        }

        try {
            return $this->rebuildFull();
        } catch (\Throwable) {
            return $row;
        }
    }

    private function snapshotIsStale(OmnichannelPostSellStat $row): bool
    {
        $freshSeconds = max(30, (int) config('marketplace.dashboard_cache.post_sell_fresh_seconds', 180));
        if ($row->updated_at === null) {
            return true;
        }

        return $row->updated_at->diffInSeconds(now()) > $freshSeconds;
    }

    /**
     * @return array{posted: int, eligible_to_post: int, errors: int}
     */
    private function emptyCounts(): array
    {
        return [
            'posted' => 0,
            'eligible_to_post' => 0,
            'errors' => 0,
        ];
    }

    private function columnForBucket(string $bucket): string
    {
        return match ($bucket) {
            OmnichannelPostSellStatsClassifier::BUCKET_POSTED => 'posted',
            OmnichannelPostSellStatsClassifier::BUCKET_ELIGIBLE => 'eligible_to_post',
            OmnichannelPostSellStatsClassifier::BUCKET_ERROR => 'errors',
            default => throw new \InvalidArgumentException("Unknown stats bucket {$bucket}"),
        };
    }
}