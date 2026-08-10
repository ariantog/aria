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

    public int $timeout = 600;

    public function __construct(public int $importId) {}

    public function handle(JubelioGetOrdersService $service): void
    {
        $import = Crongetorder::find($this->importId);
        if (! $import || ! $import->isRunning()) {
            return;
        }

        try {
            $service->runImport($import);
        } catch (\Throwable $e) {
            Log::error('SyncJubelioMissingOrders failed', [
                'import_id' => $this->importId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => false]);
        }
    }
}
