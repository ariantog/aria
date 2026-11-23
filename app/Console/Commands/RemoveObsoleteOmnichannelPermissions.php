<?php

namespace App\Console\Commands;

use App\Support\ObsoleteOmnichannelPermissions;
use Illuminate\Console\Command;

class RemoveObsoleteOmnichannelPermissions extends Command
{
    protected $signature = 'app:remove-obsolete-omnichannel-permissions
                            {--dry-run : List leftover rows without deleting}';

    protected $description = 'Delete leftover Spatie permissions: marketplace-webhook-view';

    public function handle(): int
    {
        try {
            $rows = ObsoleteOmnichannelPermissions::existing();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->info('No leftover omnichannel permissions found.');

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

        $deleted = ObsoleteOmnichannelPermissions::remove();
        $this->info("Deleted {$deleted} leftover omnichannel permission(s).");

        return self::SUCCESS;
    }
}