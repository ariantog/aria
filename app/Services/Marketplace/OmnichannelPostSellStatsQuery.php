<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Full-scan queries for native post-sell dashboard KPIs (reconcile / cold snapshot).
 */
class OmnichannelPostSellStatsQuery
{
    public function __construct(
        private OmnichannelPostSellEligibility $eligibility,
        private OmnichannelPostSellStatsScope $scope,
        private OmnichannelPostSellStatsClassifier $classifier,
        private MarketplaceChannelOrderOpsBucket $opsBucket,
    ) {}

    /**
     * @return array{posted: int, eligible_to_post: int, errors: int}
     */
    public function computeCountsFromDatabase(): array
    {
        if (! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')) {
            return [
                'posted' => 0,
                'eligible_to_post' => 0,
                'errors' => 0,
            ];
        }

        $scope = $this->statsScopeQuery();

        $posted = (clone $scope)
            ->where('posted_transaction_id', '>', 0)
            ->count();

        $eligible = 0;
        $errors = 0;

        foreach ((clone $scope)->where(function (Builder $q): void {
            $q->whereNull('posted_transaction_id')
                ->orWhere('posted_transaction_id', '<=', 0);
        })->cursor() as $order) {
            $bucket = $this->classifier->classify($order);
            if ($bucket === OmnichannelPostSellStatsClassifier::BUCKET_ELIGIBLE) {
                $eligible++;
            } elseif ($bucket === OmnichannelPostSellStatsClassifier::BUCKET_ERROR) {
                $errors++;
            }
        }

        return [
            'posted' => $posted,
            'eligible_to_post' => $eligible,
            'errors' => $errors,
        ];
    }

    /**
     * @return Builder<MarketplaceChannelOrder>
     */
    public function statsScopeQuery(): Builder
    {
        $query = MarketplaceChannelOrder::query()
            ->whereIn('channel', $this->eligibility->allowedChannels());

        $earliest = $this->scope->earliestRollingAnchor();
        $query->where(function (Builder $outer) use ($earliest): void {
            $outer->where('marketplace_ordered_at', '>=', $earliest)
                ->orWhere(function (Builder $inner) use ($earliest): void {
                    $inner->where(function (Builder $nullAnchor): void {
                        $nullAnchor->whereNull('marketplace_ordered_at')
                            ->orWhere('marketplace_ordered_at', '');
                    })->where('last_event_at', '>=', $earliest);
                })
                ->orWhere(function (Builder $inner) use ($earliest): void {
                    $inner->where(function (Builder $nullBoth): void {
                        $nullBoth->whereNull('marketplace_ordered_at')
                            ->orWhere('marketplace_ordered_at', '');
                    })->where(function (Builder $nullLast): void {
                        $nullLast->whereNull('last_event_at')
                            ->orWhere('last_event_at', '');
                    })->where('first_received_at', '>=', $earliest);
                });
        });

        if ($this->eligibility->gateMode() === 'awb_printed') {
            $query->whereNotNull('awb_printed_at');
        }

        $query->where(function (Builder $outer): void {
            $first = true;
            foreach ([
                MarketplaceChannelOrderOpsBucket::BUCKET_IN_TRANSIT,
                MarketplaceChannelOrderOpsBucket::BUCKET_COMPLETED,
            ] as $bucket) {
                $boolean = $first ? 'where' : 'orWhere';
                $first = false;
                $outer->{$boolean}(function (Builder $scoped) use ($bucket): void {
                    $this->opsBucket->applyOpsBucketFilter($scoped, $bucket, 'all');
                });
            }
        });

        return $query;
    }
}