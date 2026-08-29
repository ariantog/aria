<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class CleanupDataRetentionYear extends Command
{
    protected $signature = 'app:cleanup-data-retention-year
                            {year : Calendar year to remove from the live database}
                            {--dry-run : Preview counts without deleting}
                            {--force : Skip interactive confirmation}';

    protected $description = 'Drop an archived calendar year from the live database (partition drop when available)';

    public function handle(DataRetentionService $retention): int
    {
        $year = (int) $this->argument('year');
        $preview = $retention->previewLiveCleanup($year);

        $this->table(
            ['Metric', 'Count'],
            collect($preview)
                ->except('year', 'uses_partition_drop')
                ->map(fn ($count, $key) => [$key, is_bool($count) ? ($count ? 'yes' : 'no') : number_format((int) $count)])
                ->values()
                ->all(),
        );

        $this->line('Partition drop: '.($preview['uses_partition_drop'] ? 'yes' : 'no (row delete fallback)'));

        if ($this->option('dry-run')) {
            $this->info("Dry run only — year {$year} was not removed from live.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently remove year {$year} from the LIVE database?", false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $result = $retention->cleanupLiveYear($year);

        $this->info(sprintf(
            'Cleaned year %d from live: %d transaction(s), %d detail(s), %d orphan item(s) purged.',
            $year,
            $result['transactions'],
            $result['details'],
            $result['items_purged'],
        ));

        return self::SUCCESS;
    }
}
