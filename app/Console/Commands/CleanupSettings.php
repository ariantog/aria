<?php

namespace App\Console\Commands;

use App\Services\SettingsCleanupService;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Command;

class CleanupSettings extends Command
{
    protected $signature = 'settings:cleanup
                            {--dry-run : Show duplicate/legacy rows that would be removed}
                            {--seed : Re-seed managed settings after cleanup}';

    protected $description = 'Remove duplicate and legacy settings rows (start_time, stop_time, etc.) without running migrations';

    public function handle(SettingsCleanupService $cleanupService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run — no rows will be deleted.');
        }

        $counts = $cleanupService->run($dryRun);

        $this->table(
            ['Category', 'Rows'],
            [
                ['Duplicate slug rows', $counts['duplicate_rows']],
                ['Legacy start/stop/bottom_line rows', $counts['legacy_rows']],
                ['Deprecated invoice text rows', $counts['invoice_rows']],
                ['Other unmanaged rows', $counts['unmanaged_rows']],
            ],
        );

        if ($dryRun) {
            $this->comment('Run without --dry-run to apply the cleanup.');

            return Command::SUCCESS;
        }

        if ((bool) $this->option('seed')) {
            $this->call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);
            $this->info('Managed settings re-seeded.');
        }

        $this->info('Settings cleanup complete.');

        return Command::SUCCESS;
    }
}
