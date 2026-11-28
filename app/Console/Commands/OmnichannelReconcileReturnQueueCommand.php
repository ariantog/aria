<?php

namespace App\Console\Commands;

use App\Services\Marketplace\OmnichannelReturnQueueReconciler;
use Illuminate\Console\Command;

class OmnichannelReconcileReturnQueueCommand extends Command
{
    protected $signature = 'omnichannel:reconcile-return-queue
                            {--apply : Tulis link/dismiss ke database (default: dry-run)}
                            {--dismiss-old : Skip baris di luar rolling window tanpa RETURN yang cocok}
                            {--limit= : Batasi jumlah baris yang dipindai}';

    protected $description = 'Scan antrian marketplace returns: tautkan RETURN yang sudah ada atau skip backlog (tanpa posting stok).';

    public function handle(OmnichannelReturnQueueReconciler $reconciler): int
    {
        $apply = (bool) $this->option('apply');
        $dismissOld = (bool) $this->option('dismiss-old');
        $limit = $this->option('limit');
        $limitInt = is_numeric($limit) ? max(1, (int) $limit) : null;

        if (! $apply) {
            $this->warn('Dry-run — tidak ada perubahan. Gunakan --apply untuk menulis.');
        }

        $stats = $reconciler->reconcileBatch($apply, $dismissOld, $limitInt);

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Dipindai', $stats['scanned']],
                ['Bisa ditautkan (RETURN ada)', $stats['linked']],
                ['Bisa di-skip (dismiss)', $stats['dismissed']],
                ['Tidak berubah', $stats['skipped']],
            ],
        );

        foreach ($stats['errors'] as $error) {
            $this->error($error);
        }

        if ($stats['errors'] !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}