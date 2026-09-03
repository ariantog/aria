<?php

namespace App\Console\Commands;

use App\Services\TransactionService;
use Illuminate\Console\Command;

class RecalculateRunningBalances extends Command
{
    protected $signature = 'app:recalculate-running-balances
                            {--addrbook= : Only rebuild this addrbook id}
                            {--from= : Recalculate from this date onwards (Y-m-d)}';

    protected $description = 'Rebuild transaction running balances in date+id order and sync addrbook stats';

    public function handle(TransactionService $service): int
    {
        $addrbookId = $this->option('addrbook');
        $from = $this->option('from');

        $updated = $service->rebuildRunningBalances(
            $addrbookId !== null && $addrbookId !== '' ? (int) $addrbookId : null,
            $from !== null && $from !== '' ? (string) $from : null,
        );

        $this->info("Recalculated running balances on {$updated} transactions.");

        return self::SUCCESS;
    }
}
