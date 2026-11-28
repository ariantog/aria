<?php

namespace App\Console\Commands;

use App\Services\Marketplace\OmnichannelPostSellEligibility;
use App\Services\Marketplace\OmnichannelShippedOrderPoster;
use Illuminate\Console\Command;

class OmnichannelPostShippedOrdersCommand extends Command
{
    protected $signature = 'omnichannel:post-shipped-orders
                            {--channel= : Limit to one channel (shopee|tiktok)}
                            {--dry-run : Log would-post actions without creating transactions}
                            {--limit= : Override batch size}
                            {--returns : Reserved for RETURN posting (not implemented)}';

    protected $description = 'Post native marketplace SELL transactions from marketplace_channel_orders (shipped / AWB gate).';

    public function handle(
        OmnichannelShippedOrderPoster $poster,
        OmnichannelPostSellEligibility $eligibility,
    ): int {
        if ($this->option('returns')) {
            $this->warn('RETURN posting is deferred; SELL only in this release.');

            return self::SUCCESS;
        }

        $enabled = $eligibility->masterEnabled();
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
            'Gate=%s, dry_run=%s, batch=%d, channels=%s',
            $eligibility->gateMode(),
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