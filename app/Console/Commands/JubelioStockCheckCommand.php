<?php

namespace App\Console\Commands;

use App\Models\JubelioStockCheck;
use App\Models\ScheduledTask;
use App\Services\JubelioStockCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class JubelioStockCheckCommand extends Command
{
    protected $signature = 'app:jubelio-stock-check
                            {--sync : Process all remaining synced warehouses in one run}';

    protected $description = 'Compare Aria warehouse stock with Jubelio available for linked SKUs';

    public function handle(JubelioStockCheckService $service): int
    {
        $this->recordCronHeartbeat();

        $this->info('Memulai pengecekan stok Jubelio...');

        config(['services.jubelio.active' => true]);

        $job = $service->ensureDailyJob();

        if (! $job) {
            $job = JubelioStockCheck::whereIn('status', ['created', 'processing'])
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $job) {
            $this->comment('Tidak ada job pengecekan aktif dan job harian sudah dibuat hari ini.');

            return self::SUCCESS;
        }

        if ($job->status === 'created') {
            $job->update(['status' => 'processing']);
        }

        $syncs = $service->syncedWarehouses();
        if ($syncs === []) {
            $this->warn('Tidak ada warehouse yang tersinkron di jubeliosyncs.');
            $job->update(['status' => 'completed']);

            return self::SUCCESS;
        }

        try {
            do {
                $result = $service->processNextWarehouse($job);
                $job->refresh();

                if ($result['warehouse']) {
                    $this->info(sprintf(
                        'Gudang %s (ronde %d): %d SKU dicek, %d selisih baru, %d total selisih.',
                        $result['warehouse'],
                        $job->scan_round,
                        $result['checked'],
                        $result['discrepancies'],
                        $job->discrepancies()->count(),
                    ));
                }
            } while ($this->option('sync') && ! $result['done']);

            if ($result['done']) {
                $this->info(sprintf(
                    'Pengecekan selesai — %d ketidakcocokan (target %d).',
                    $job->discrepancies()->count(),
                    $job->target_discrepancies ?: JubelioStockCheckService::DEFAULT_TARGET_DISCREPANCIES,
                ));
            } else {
                $remaining = count($syncs) - $job->sync_cursor;
                $this->comment("{$remaining} gudang tersisa — lanjutkan cron berikutnya.");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('app:jubelio-stock-check failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function recordCronHeartbeat(): void
    {
        ScheduledTask::query()
            ->where(function ($query) {
                $query->where('command', 'app:jubelio-stock-check')
                    ->orWhere('command', 'app:jubelio-stock-check --single');
            })
            ->update(['last_run_at' => now()]);
    }
}
