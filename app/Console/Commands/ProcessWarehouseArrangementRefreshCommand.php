<?php

namespace App\Console\Commands;

use App\Models\WarehouseArrangementRefreshJob;
use App\Services\WarehouseArrangementRefreshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWarehouseArrangementRefreshCommand extends Command
{
    protected $signature = 'app:process-warehouse-arrangement-refresh
                            {--batch= : Number of SKUs to process per job per run}';

    protected $description = 'Process queued warehouse arrangement refresh jobs in the background';

    public function handle(WarehouseArrangementRefreshService $service): int
    {
        $batchSize = (int) ($this->option('batch') ?: WarehouseArrangementRefreshService::BATCH_SIZE);
        $batchSize = max(1, $batchSize);

        $jobs = WarehouseArrangementRefreshJob::query()
            ->whereIn('status', [
                WarehouseArrangementRefreshJob::STATUS_CREATED,
                WarehouseArrangementRefreshJob::STATUS_PROCESSING,
            ])
            ->orderBy('created_at')
            ->get();

        if ($jobs->isEmpty()) {
            $this->comment('No warehouse arrangement refresh jobs waiting.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            try {
                $result = $service->processNextBatch($job, $batchSize);
                $job->refresh();

                $warehouseName = $job->destinationWarehouse?->name ?? ('#'.$job->destination_warehouse_id);

                if ($result['phase'] === WarehouseArrangementRefreshJob::PHASE_STATS && $result['processed'] > 0) {
                    $this->info(sprintf(
                        'Warehouse %s: processed %d SKU(s) (%d/%d, %d stat row(s)).',
                        $warehouseName,
                        $result['processed'],
                        $job->item_cursor,
                        $job->total_items,
                        $job->stats_rows_inserted,
                    ));
                } elseif ($result['done'] && $job->status === WarehouseArrangementRefreshJob::STATUS_COMPLETED) {
                    $this->info(sprintf('Warehouse %s: refresh completed. %s', $warehouseName, $job->result_message));
                } elseif ($job->status === WarehouseArrangementRefreshJob::STATUS_FAILED) {
                    $this->error(sprintf('Warehouse %s: refresh failed. %s', $warehouseName, $job->error_message));
                } elseif ($result['phase'] === WarehouseArrangementRefreshJob::PHASE_SYNC && ! $result['done']) {
                    $this->comment(sprintf('Warehouse %s: stats finished, running cache sync…', $warehouseName));
                }
            } catch (\Throwable $e) {
                Log::error('app:process-warehouse-arrangement-refresh failed', [
                    'job_id' => $job->id,
                    'warehouse_id' => $job->destination_warehouse_id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $service->failJob($job, $e->getMessage());
                $this->error(sprintf('Job #%d failed: %s', $job->id, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
