<?php

namespace App\Jobs;

use App\Models\Crongetorder;
use App\Models\ScheduledTask;
use App\Services\JubelioGetOrdersService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncJubelioMissingOrders implements ShouldQueue
{
    use Queueable;

    /**
     * Production drains the queue with `queue:work --max-time=55` every minute.
     * A 600s full-import job is killed / retried as stale (retry_after=90, tries=1)
     * and left the Crongetorder row running at whatever page it last saved.
     */
    public int $timeout = 50;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 20, 30];

    public function __construct(public int $importId) {}

    public function handle(JubelioGetOrdersService $service): void
    {
        $import = Crongetorder::find($this->importId);
        if (! $import || ! $import->isRunning()) {
            $this->disableScheduledTask();

            return;
        }

        try {
            $result = $service->processBatch($import, 3, 40);
        } catch (\Throwable $e) {
            Log::error('SyncJubelioMissingOrders failed', [
                'import_id' => $this->importId,
                'message' => $e->getMessage(),
            ]);
            $this->enableScheduledTask();

            throw $e;
        }

        $import->refresh();

        if ($import->isRunning() && ! $result['completed']) {
            $this->enableScheduledTask();
            static::dispatch($this->importId)->delay(now()->addSeconds(8));

            return;
        }

        $this->disableScheduledTask();
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SyncJubelioMissingOrders exhausted retries', [
            'import_id' => $this->importId,
            'message' => $exception?->getMessage(),
        ]);

        $this->enableScheduledTask();
    }

    protected function enableScheduledTask(): void
    {
        ScheduledTask::where('command', 'jubelio:get-orders')->update(['active' => true]);
    }

    protected function disableScheduledTask(): void
    {
        ScheduledTask::where('command', 'jubelio:get-orders')->update(['active' => false]);
    }
}
