<?php

namespace App\Console\Commands;

use App\Models\MarketplaceWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneMarketplaceWebhookEventsCommand extends Command
{
    protected $signature = 'marketplace:prune-webhook-events
                            {--days= : Delete events older than this many days (default from config)}
                            {--dry-run : Count rows only, do not delete}';

    protected $description = 'Delete marketplace_webhook_events rows older than the retention window (batch deletes)';

    private const BATCH_SIZE = 5000;

    public function handle(): int
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            $this->warn('Table marketplace_webhook_events does not exist.');

            return self::SUCCESS;
        }

        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? max(1, (int) $daysOption)
            : (int) config('marketplace.webhook.events_retention_days', 90);
        $days = max(1, $days);

        $cutoff = now()->subDays($days)->startOfSecond();

        $query = MarketplaceWebhookEvent::query()->where('received_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info(sprintf('No webhook event rows older than %s.', $cutoff->toDateTimeString()));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Would delete %d marketplace_webhook_events row(s) with received_at before %s (%d day retention).',
                $count,
                $cutoff->toDateTimeString(),
                $days,
            ));

            return self::SUCCESS;
        }

        $deleted = 0;

        while (true) {
            $batchIds = MarketplaceWebhookEvent::query()
                ->where('received_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($batchIds->isEmpty()) {
                break;
            }

            $deleted += MarketplaceWebhookEvent::query()->whereIn('id', $batchIds)->delete();

            if ($batchIds->count() < self::BATCH_SIZE) {
                break;
            }
        }

        $this->info(sprintf(
            'Deleted %d marketplace_webhook_events row(s) older than %s (%d day retention).',
            $deleted,
            $cutoff->toDateTimeString(),
            $days,
        ));

        return self::SUCCESS;
    }
}