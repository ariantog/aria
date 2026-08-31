<?php

namespace App\Console\Commands;

use App\Support\ObsoleteReportPermissions;
use Illuminate\Console\Command;

class RemoveObsoleteReportPermissions extends Command
{
    protected $signature = 'app:remove-obsolete-report-permissions
                            {--dry-run : List leftover rows without deleting}';

    protected $description = 'Delete leftover Spatie permissions: report-cash-flow, report-expense, report-purchase, cash-flow';

    public function handle(): int
    {
        $rows = ObsoleteReportPermissions::existing();

        if ($rows->isEmpty()) {
            $this->info('No leftover report permissions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name'],
            $rows->map(fn ($row) => [$row->id, $row->name])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no rows were deleted.');

            return self::SUCCESS;
        }

        $deleted = ObsoleteReportPermissions::remove();
        $this->info("Deleted {$deleted} leftover report permission(s).");

        return self::SUCCESS;
    }
}
