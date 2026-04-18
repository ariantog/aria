<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TruncateTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:truncate-transactions {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate transactions and transaction_details tables safely.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to truncate transactions and transaction_details? This will delete ALL transaction data!')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Truncating transaction tables...');

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('transaction_details')->truncate();
            $this->info('transaction_details table truncated.');

            DB::table('transactions')->truncate();
            $this->info('transactions table truncated.');

            // Also consider truncating related tables if they are strictly dependent on transactions
            // For example, if there are inventory summaries or daily reports that depend on these.
            // But based on request, we only truncate these two.

            $this->info('Successfully truncated all transaction tables.');
        } catch (\Exception $e) {
            $this->error('Failed to truncate tables: ' . $e->getMessage());
            return 1;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return 0;
    }
}
