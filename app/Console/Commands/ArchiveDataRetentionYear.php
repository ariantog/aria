<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class ArchiveDataRetentionYear extends Command
{
    protected $signature = 'app:archive-data-retention-year
                            {year? : Calendar year to copy to the archive database}
                            {--dry-run : Preview counts without writing}';

    protected $description = 'Copy one calendar year of transactions (and related rows) to the archive database';

    public function handle(DataRetentionService $retention): int
    {
        $year = $this->resolveYear($retention);

        if ($year === null) {
            $this->error('No eligible year found. Pass a year argument or ensure live data exists outside the retention window.');

            return self::FAILURE;
        }

        $preview = $retention->previewArchiveYear($year);
        $this->table(
            ['Metric', 'Count'],
            collect($preview)->except('year')->map(fn ($count, $key) => [$key, number_format($count)])->values()->all(),
        );

        if ($this->option('dry-run')) {
            $this->info("Dry run only — year {$year} was not copied.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Copy year {$year} to the archive database?", true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $result = $retention->archiveYear($year);

        $this->info(sprintf(
            'Archived year %d: %d transaction(s), %d detail(s), %d customer(s), %d item(s).',
            $year,
            $result['transactions'],
            $result['details'],
            $result['customers'],
            $result['items'],
        ));

        return self::SUCCESS;
    }

    protected function resolveYear(DataRetentionService $retention): ?int
    {
        $argument = $this->argument('year');

        if ($argument !== null) {
            return (int) $argument;
        }

        $eligible = $retention->yearsEligibleForArchive();

        return $eligible[0] ?? null;
    }
}
