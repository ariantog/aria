<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-transactions 
                            {--date= : Specific date to delete (YYYY-MM-DD)} 
                            {--year= : Specific year to delete (YYYY)} 
                            {--dry-run : Only show the count of records to be deleted}
                            {--force : Bypasses confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete transactions and their details based on date or year';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date');
        $year = $this->option('year');
        $isDryRun = $this->option('dry-run');

        if (! $date && ! $year) {
            $this->error('You must provide either a --date or a --year option.');

            return Command::FAILURE;
        }

        $query = Transaction::query();

        if ($date) {
            $query->whereDate('date', $date);
            $filterInfo = "Date: {$date}";
        } else {
            $query->whereYear('date', $year);
            $filterInfo = "Year: {$year}";
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info("No transactions found for {$filterInfo}.");

            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("[DRY RUN] Found {$count} transactions to be deleted for {$filterInfo}.");

            return Command::SUCCESS;
        }

        $this->warn("CAUTION: This will permanently delete {$count} transactions and all their associated details for {$filterInfo}.");

        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure you want to proceed?', false)) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            DB::transaction(function () use ($query) {
                // We use each() and delete() to ensure model events are fired if needed,
                // though mass delete is faster. Given cascade, we can mass delete.
                // However, the user said "delete transaction detail nya sesuai relasi",
                // and cascade is set at DB level.
                $query->delete();
            });

            $this->info("Successfully deleted {$count} transactions.");
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
