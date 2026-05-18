<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\JubelioStockDiscrepancy;
use App\Models\Jubeliosync;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Illuminate\Console\Command;

class JubelioStockCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:jubelio-stock-check {--page= : Start from a specific page}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Penyocokan stok Aria dengan Jubelio';

    /**
     * Execute the console command.
     */
    public function handle(JubelioService $jubelioService): int
    {
        $this->info('Memulai pengecekan stok Jubelio...');

        // Force enable service and disable SSL verification for this command
        config(['services.jubelio.active' => true]);
        config(['services.jubelio.verify_ssl' => false]);

        // 1. Dapatkan atau buat job master
        $job = JubelioStockCheck::whereIn('status', ['created', 'processing'])->orderBy('created_at', 'desc')->first();

        if (! $job) {
            $job = JubelioStockCheck::create([
                'page_tracking' => $this->option('page') ?: 1,
                'status' => 'processing',
            ]);
        } elseif ($this->option('page')) {
            $job->update(['page_tracking' => $this->option('page')]);
        }

        if ($job->status === 'created') {
            $job->update(['status' => 'processing']);
        }

        $pageSize = 50;
        $totalDiscrepancies = $job->discrepancies()->count();

        if ($totalDiscrepancies >= 200) {
            $this->warn('Job sudah memiliki 200 atau lebih ketidakcocokan. Menghentikan.');
            $job->update(['status' => 'stopped']);

            return 0;
        }

        // PROSES HANYA SATU HALAMAN (UNTUK CRON)
        $this->info("Menghubungi Jubelio API (Halaman: {$job->page_tracking})...");

        $response = $jubelioService->fetchInventory($job->page_tracking, $pageSize);

        if (! $response) {
            $this->error('Gagal: Koneksi ke Jubelio API tidak mengembalikan respon (null).');

            return 1;
        }

        if (isset($response['error'])) {
            $this->error('Gagal dari Jubelio: '.($response['error']['message'] ?? 'Unknown Error'));
            if (isset($response['error']['raw'])) {
                $this->warn('Raw Error Message: '.$response['error']['raw']);
            }
            if (isset($response['statusCode'])) {
                $this->error('Status Code: '.$response['statusCode']);
            }

            return 1;
        }

        if (! isset($response['data'])) {
            $this->warn('Koneksi Berhasil, tapi format data tidak sesuai: '.json_encode($response));

            return 1;
        }

        $itemCount = count($response['data']);
        $this->info("Koneksi Berhasil! Diterima {$itemCount} item dari Jubelio.");

        if ($itemCount === 0) {
            $this->info('Pengecekan selesai: Tidak ada data lagi untuk diproses dari Jubelio.');
            $job->update(['status' => 'completed']);

            return 0;
        }

        foreach ($response['data'] as $jubelioItem) {
            $jubelioItemId = $jubelioItem['item_id'];

            // Cari item di Aria berdasarkan jubelio_item_id
            $ariaItem = Item::where('jubelio_item_id', $jubelioItemId)->first();

            if (! $ariaItem) {
                continue;
            }

            foreach ($jubelioItem['location_stocks'] as $locStock) {
                $jubelioLocId = $locStock['location_id'];
                $jubelioQty = $locStock['on_hand'];

                // Cari pemetaan warehouse di Jubeliosync
                $sync = Jubeliosync::where('jubelio_location_id', $jubelioLocId)->first();

                if (! $sync) {
                    continue;
                }

                $warehouseId = $sync->warehouse_id;

                // Ambil qty di Aria
                $ariaQty = WarehouseItem::where('item_id', $ariaItem->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first()?->quantity ?? 0;

                if ((float) $ariaQty != (float) $jubelioQty) {
                    JubelioStockDiscrepancy::create([
                        'jubelio_stock_check_id' => $job->id,
                        'jubelio_item_id' => $jubelioItemId,
                        'jubelio_location_id' => $jubelioLocId,
                        'warehouse_id' => $warehouseId,
                        'aria_qty' => $ariaQty,
                        'jubelio_qty' => $jubelioQty,
                    ]);

                    $totalDiscrepancies++;

                    if ($totalDiscrepancies >= 200) {
                        $this->warn('Mencapai batas 200 ketidakcocokan. Menghentikan.');
                        $job->update([
                            'status' => 'stopped',
                            'page_tracking' => $job->page_tracking + 1, // Tetap simpan progress halaman terakhir
                        ]);

                        return 0;
                    }
                }
            }
        }

        // Update page tracking untuk dijalankan cron berikutnya
        $job->increment('page_tracking');

        $this->info('Halaman '.($job->page_tracking - 1).' selesai diproses. Page tracking sekarang: '.$job->page_tracking);
        $this->info("Total ketidakcocokan saat ini: {$totalDiscrepancies}");

        return 0;
    }
}
