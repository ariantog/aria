<?php

namespace App\Console\Commands;

use App\Models\JubelioStockCheck;
use App\Services\JubelioStockCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class JubelioStockCheckCommand extends Command
{
    protected $signature = 'app:jubelio-stock-check
                            {--sync : Process all remaining synced warehouses in one run}';

    protected $description = 'Compare Aria warehouse stock with Jubelio on-hand for high-demand SKUs';

    public function handle(JubelioStockCheckService $service): int
    {
        $this->info('Memulai pengecekan stok Jubelio...');

        config(['services.jubelio.active' => true]);

        $job = JubelioStockCheck::whereIn('status', ['created', 'processing'])
            ->orderByDesc('created_at')
            ->first();

        if (! $job) {
            $this->comment('Tidak ada job pengecekan aktif.');

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
                        'Gudang %s: %d SKU dicek, %d selisih.',
                        $result['warehouse'],
                        $result['checked'],
                        $result['discrepancies'],
                    ));
                }
            } while ($this->option('sync') && ! $result['done']);

            if ($result['done']) {
                $this->info('Pengecekan selesai untuk semua gudang tersinkron.');
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
}
