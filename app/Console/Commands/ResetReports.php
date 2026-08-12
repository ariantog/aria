<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-reports {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all stock and warehouse reports data to zero/empty';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to reset all stock reports? This will empty warehouse_item and set all item quantities to zero.')) {
            $this->info('Reset cancelled.');

            return Command::SUCCESS;
        }

        $this->warn('Starting reset of stock reports data...');

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () {
                $this->info('Emptying warehouse_item table...');
                DB::table('warehouse_item')->truncate();

                $this->info('Resetting global item quantities to zero...');
                DB::table('items')->update(['qty' => 0]);

                // Optional: reset other report tables if needed
                // DB::table('customerstat')->update(['balance' => 0]);
                // DB::table('customer_class')->truncate();
            });

            $this->info('Reports data has been reset successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Reset failed: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
