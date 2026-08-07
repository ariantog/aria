<?php

namespace App\Console\Commands;

use App\Models\Crongetorder;
use App\Models\ScheduledTask;
use App\Services\JubelioGetOrdersService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetOrderJubelio extends Command
{
    protected $signature = 'jubelio:get-orders
                            {--pages=10 : Max API pages to fetch per run}
                            {--timeout=50 : Max seconds per run}
                            {--sync : Fetch all remaining pages in one run}';

    protected $description = 'Pull Jubelio sales orders by date range and reconcile missing webhooks';

    public function handle(JubelioGetOrdersService $service): int
    {
        Log::info('jubelio:get-orders run at: '.now());

        $import = Crongetorder::orderByDesc('created_at')->first();

        if (! $import) {
            $this->comment('No active import job.');

            return self::SUCCESS;
        }

        if (! $import->isRunning()) {
            $this->comment('Import job already finished.');

            return self::SUCCESS;
        }

        try {
            if ($this->option('sync')) {
                $service->processSync($import, (int) $this->option('pages'));
                $import->refresh();
                $this->info('Sync complete. '.$import->details()->count().' missing orders remain.');
            } else {
                $result = $service->processBatch(
                    $import,
                    (int) $this->option('pages'),
                    (int) $this->option('timeout'),
                );

                if ($result['fetched_pages'] > 0) {
                    $this->info("Fetched {$result['fetched_pages']} page(s).");
                }

                if ($result['completed']) {
                    $this->info('Import complete — remaining rows are missing from Aria.');
                } elseif ($result['remaining'] !== null && $result['remaining'] > 0) {
                    $this->comment("{$result['remaining']} page(s) remaining.");
                }
            }

            $import->refresh();
            if ($import->status === 1) {
                $this->disableScheduledTask();
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('jubelio:get-orders failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function disableScheduledTask(): void
    {
        ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => false]);
    }
}
