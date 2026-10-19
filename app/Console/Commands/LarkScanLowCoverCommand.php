<?php

namespace App\Console\Commands;

use App\Services\Lark\LowCoverScanService;
use Illuminate\Console\Command;

class LarkScanLowCoverCommand extends Command
{
    protected $signature = 'lark:scan-low-cover {--dry-run : Log only; does not change LARK_DRY_RUN}';

    protected $description = 'Scan SKU cover (Aria + Jubelio) and send Lark alerts / restock approval cards';

    public function handle(LowCoverScanService $scanner): int
    {
        if ($this->option('dry-run')) {
            config(['lark.dry_run' => true]);
        }

        $count = $scanner->run();
        $this->info("Low-cover scan dispatched {$count} new alert(s).");

        return self::SUCCESS;
    }
}
