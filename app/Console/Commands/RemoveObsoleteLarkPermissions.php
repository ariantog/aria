<?php

namespace App\Console\Commands;

use App\Support\ObsoleteLarkPermissions;
use Illuminate\Console\Command;

class RemoveObsoleteLarkPermissions extends Command
{
    protected $signature = 'app:remove-obsolete-lark-permissions
                            {--dry-run : List leftover rows without deleting}';

    protected $description = 'Delete leftover Spatie permissions from the removed Lark ops integration';

    public function handle(): int
    {
        try {
            $rows = ObsoleteLarkPermissions::existing();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->info('No leftover Lark permissions found.');

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

        $deleted = ObsoleteLarkPermissions::remove();
        $this->info("Deleted {$deleted} leftover Lark permission(s).");

        return self::SUCCESS;
    }
}