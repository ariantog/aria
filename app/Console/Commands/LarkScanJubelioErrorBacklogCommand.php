<?php

namespace App\Console\Commands;

use App\Services\Lark\JubelioErrorBacklogScanService;
use Illuminate\Console\Command;

class LarkScanJubelioErrorBacklogCommand extends Command
{
    protected $signature = 'lark:scan-jubelio-error-backlog {--dry-run : Log only; does not change LARK_DRY_RUN}';

    protected $description = 'Alert when Jubelio order sync error backlog exceeds Lark Ops threshold';

    public function handle(JubelioErrorBacklogScanService $scanner): int
    {
        if ($this->option('dry-run')) {
            config(['lark.dry_run' => true]);
        }

        $scanner->run();
        $count = $scanner->attentionErrorCount();
        $threshold = (int) app(\App\Services\Lark\LarkSettingsService::class)->jubelio()['error_backlog_threshold'];
        $this->info("Jubelio error backlog: {$count} (threshold {$threshold}).");

        return self::SUCCESS;
    }
}
