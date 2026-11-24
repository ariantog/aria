<?php

namespace App\Console\Commands;

use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use App\Models\TikTokShopWebhookInbox;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class PruneMarketplaceWebhookEventsCommand extends Command
{
    protected $signature = 'marketplace:prune-webhook-events
                            {--days= : Delete rows older than this many days (default from config)}
                            {--dry-run : Count rows only, do not delete}';

    protected $description = 'Prune marketplace_webhook_events and legacy Shopee/TikTok webhook inbox tables by retention window';

    private const BATCH_SIZE = 5000;

    public function handle(): int
    {
        $days = $this->resolveRetentionDays();
        $cutoff = now()->subDays($days)->startOfSecond();
        $dryRun = (bool) $this->option('dry-run');

        $totalDeleted = 0;
        $totalWouldDelete = 0;

        if (Schema::hasTable('marketplace_webhook_events')) {
            [$deleted, $wouldDelete] = $this->pruneTable(
                MarketplaceWebhookEvent::query(),
                'received_at',
                $cutoff,
                $dryRun,
                'marketplace_webhook_events',
            );
            $totalDeleted += $deleted;
            $totalWouldDelete += $wouldDelete;
        } else {
            $this->warn('Table marketplace_webhook_events does not exist.');
        }

        if (Schema::hasTable('shopee_push_inbox')) {
            [$deleted, $wouldDelete] = $this->pruneTable(
                ShopeePushInbox::query(),
                'created_at',
                $cutoff,
                $dryRun,
                'shopee_push_inbox',
            );
            $totalDeleted += $deleted;
            $totalWouldDelete += $wouldDelete;
        }

        if (Schema::hasTable('tiktok_shop_webhook_inbox')) {
            [$deleted, $wouldDelete] = $this->pruneTable(
                TikTokShopWebhookInbox::query(),
                'created_at',
                $cutoff,
                $dryRun,
                'tiktok_shop_webhook_inbox',
            );
            $totalDeleted += $deleted;
            $totalWouldDelete += $wouldDelete;
        }

        if ($dryRun) {
            if ($totalWouldDelete === 0) {
                $this->info(sprintf('No webhook/inbox rows older than %s.', $cutoff->toDateTimeString()));
            } else {
                $this->info(sprintf(
                    'Would delete %d webhook/inbox row(s) before %s (%d day retention).',
                    $totalWouldDelete,
                    $cutoff->toDateTimeString(),
                    $days,
                ));
            }
        } elseif ($totalDeleted === 0) {
            $this->info(sprintf('No webhook/inbox rows older than %s.', $cutoff->toDateTimeString()));
        } else {
            $this->info(sprintf(
                'Deleted %d webhook/inbox row(s) older than %s (%d day retention).',
                $totalDeleted,
                $cutoff->toDateTimeString(),
                $days,
            ));
        }

        return self::SUCCESS;
    }

    private function resolveRetentionDays(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? max(1, (int) $daysOption)
            : (int) config('marketplace.webhook.events_retention_days', 90);

        return max(1, $days);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Model>  $query
     * @return array{0: int, 1: int} deleted count, would-delete count for dry-run
     */
    private function pruneTable(
        $query,
        string $timestampColumn,
        CarbonInterface $cutoff,
        bool $dryRun,
        string $label,
    ): array {
        $scoped = (clone $query)->where($timestampColumn, '<', $cutoff);
        $count = (clone $scoped)->count();

        if ($count === 0) {
            return [0, 0];
        }

        if ($dryRun) {
            $this->line(sprintf('  %s: would delete %d row(s).', $label, $count));

            return [0, $count];
        }

        $deleted = 0;

        while (true) {
            $batchIds = (clone $query)
                ->where($timestampColumn, '<', $cutoff)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($batchIds->isEmpty()) {
                break;
            }

            $deleted += (clone $query)->whereIn('id', $batchIds)->delete();
            $this->line(sprintf('  %s: deleted %d row(s) (batch).', $label, $batchIds->count()));

            if ($batchIds->count() < self::BATCH_SIZE) {
                break;
            }
        }

        return [$deleted, 0];
    }
}