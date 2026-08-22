<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessQueue extends Command
{
    protected $signature = 'app:process-queue';

    protected $description = 'Drain pending database queue jobs (intended for schedule:run / cron-manager)';

    public function handle(): int
    {
        $exit = $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 55,
        ]);

        $this->call('app:process-warehouse-arrangement-refresh');

        return $exit;
    }
}
