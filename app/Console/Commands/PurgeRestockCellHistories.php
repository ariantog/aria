<?php

namespace App\Console\Commands;

use App\Models\RestockCellHistory;
use Illuminate\Console\Command;

class PurgeRestockCellHistories extends Command
{
    protected $signature = 'app:purge-restock-cell-histories
                            {--months=6 : Delete history rows older than this many months}
                            {--dry-run : Count rows only, do not delete}';

    protected $description = 'Delete restock cell change history older than the retention window (default 6 months)';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months)->startOfDay();

        $query = RestockCellHistory::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info(sprintf('No restock history rows older than %s.', $cutoff->toDateString()));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Would delete %d restock history row(s) with created_at before %s.',
                $count,
                $cutoff->toDateString(),
            ));

            return self::SUCCESS;
        }

        $deleted = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$deleted) {
            $ids = $rows->pluck('id')->all();
            $deleted += RestockCellHistory::query()->whereIn('id', $ids)->delete();
        });

        $this->info(sprintf(
            'Deleted %d restock history row(s) older than %s (%d month retention).',
            $deleted,
            $cutoff->toDateString(),
            $months,
        ));

        return self::SUCCESS;
    }
}
