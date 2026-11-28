<?php

namespace App\Console\Commands;

use App\Services\Marketplace\OmnichannelPostReturnEligibility;
use App\Services\Marketplace\OmnichannelPostSellEligibility;
use App\Services\Marketplace\OmnichannelReturnOrderPoster;
use App\Services\Marketplace\OmnichannelShippedOrderPoster;
use Illuminate\Console\Command;

class OmnichannelPostShippedOrdersCommand extends Command
{
    protected $signature = 'omnichannel:post-shipped-orders
                            {--channel= : Limit to one channel (shopee|tiktok)}
                            {--dry-run : Log would-post actions without creating transactions}
                            {--limit= : Override batch size}
                            {--returns : Post RETURN transactions (cancelled / TO_RETURN gate) instead of SELL}';

    protected $description = 'Post native marketplace SELL or RETURN transactions from marketplace_channel_orders.';

    public function handle(
        OmnichannelShippedOrderPoster $sellPoster,
        OmnichannelReturnOrderPoster $returnPoster,
        OmnichannelPostSellEligibility $sellEligibility,
        OmnichannelPostReturnEligibility $returnEligibility,
    ): int {
        if ($this->option('returns')) {
            return $this->runReturns($returnPoster, $returnEligibility);
        }

        $enabled = $sellEligibility->masterEnabled();
        $dryRun = (bool) $this->option('dry-run');

        if (! $enabled && ! $dryRun) {
            $this->error('OMNICHANNEL_POST_SELLS is false. Use --dry-run to shadow-test without posting.');

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : (int) config('marketplace.post_sell.batch_size', 25);

        $channel = $this->option('channel');
        if (is_string($channel) && $channel !== '') {
            $channel = strtolower(trim($channel));
        } else {
            $channel = null;
        }

        $this->info(sprintf(
            'SELL gate=%s, dry_run=%s, batch=%d, channels=%s',
            $sellEligibility->gateMode(),
            $dryRun || $sellEligibility->configDryRun() ? 'yes' : 'no',
            $limit,
            implode(',', $sellEligibility->allowedChannels())
        ));

        $stats = $sellPoster->runBatch($channel, $limit, $dryRun);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except('errors')
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        foreach ($stats['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }

    private function runReturns(
        OmnichannelReturnOrderPoster $poster,
        OmnichannelPostReturnEligibility $eligibility,
    ): int {
        $enabled = $eligibility->masterEnabled();
        $dryRun = (bool) $this->option('dry-run');

        if (! $enabled && ! $dryRun) {
            $this->error('OMNICHANNEL_POST_RETURNS is false. Use --dry-run to shadow-test without posting.');

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : (int) config('marketplace.post_return.batch_size', 25);

        $channel = $this->option('channel');
        if (is_string($channel) && $channel !== '') {
            $channel = strtolower(trim($channel));
        } else {
            $channel = null;
        }

        $this->info(sprintf(
            'RETURN gate=cancelled/TO_RETURN, dry_run=%s, batch=%d, channels=%s',
            $dryRun || $eligibility->configDryRun() ? 'yes' : 'no',
            $limit,
            implode(',', $eligibility->allowedChannels())
        ));

        $stats = $poster->runBatch($channel, $limit, $dryRun);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except('errors')
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        foreach ($stats['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}