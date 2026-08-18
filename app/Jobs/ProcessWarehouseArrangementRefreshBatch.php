<?php

namespace App\Jobs;

use App\Models\WarehouseArrangementRefreshJob;
use App\Services\WarehouseArrangementRefreshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWarehouseArrangementRefreshBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public int $refreshJobId) {}

    public function handle(WarehouseArrangementRefreshService $service): void
    {
        $job = WarehouseArrangementRefreshJob::find($this->refreshJobId);
        if (! $job || ! $job->isActive()) {
            return;
        }

        try {
            $service->processNextBatch($job);
            $job->refresh();

            if ($job->isActive() && $job->phase === WarehouseArrangementRefreshJob::PHASE_STATS) {
                self::dispatch($this->refreshJobId);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessWarehouseArrangementRefreshBatch failed', [
                'refresh_job_id' => $this->refreshJobId,
                'message' => $e->getMessage(),
            ]);

            $service->failJob($job, $e->getMessage());
        }
    }
}
