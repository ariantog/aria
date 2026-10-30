<?php

namespace App\Console\Commands;

use App\Support\ObsoleteL10Permissions;
use Illuminate\Console\Command;

class RemoveObsoleteL10Permissions extends Command
{
    protected $signature = 'app:remove-obsolete-l10-permissions
                            {--dry-run : List leftover rows without deleting}';

    protected $description = 'Delete Spatie permissions that are not part of the L12 permission registry (legacy L10 leftovers)';

    public function handle(): int
    {
        try {
            $rows = ObsoleteL10Permissions::existing();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->info('No leftover L10 / orphan permissions found.');

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

        $deleted = ObsoleteL10Permissions::remove();
        $this->info("Deleted {$deleted} leftover permission(s).");

        return self::SUCCESS;
    }
}
