<?php

namespace App\Console\Commands;

use App\Models\RestockSheet;
use App\Services\Restock\RestockSheetParentTotalsSync;
use Illuminate\Console\Command;

class SyncRestockSheetParentTotals extends Command
{
    protected $signature = 'app:sync-restock-sheet-parent-totals
                            {--sheet= : Restock sheet id to refresh (default: all sheets)}';

    protected $description = 'Rebuild cached per-parent pipeline totals for restock sheets';

    public function handle(RestockSheetParentTotalsSync $sync): int
    {
        $sheetId = $this->option('sheet');

        $query = RestockSheet::query()->orderBy('id');
        if ($sheetId !== null && $sheetId !== '') {
            $query->whereKey((int) $sheetId);
        }

        $count = 0;
        $query->each(function (RestockSheet $sheet) use ($sync, &$count) {
            $sync->syncSheet($sheet);
            $count++;
        });

        $this->info(sprintf('Refreshed parent totals for %d restock sheet(s).', $count));

        return self::SUCCESS;
    }
}
