<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetLegacyItemsMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-legacy-items-migration {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the results of the legacy items migration by truncating item-related tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to reset the legacy items migration? This will truncate items, item_groups, tags, and item_tag tables.')) {
            $this->info('Reset cancelled.');

            return Command::SUCCESS;
        }

        $this->warn('Starting reset of legacy items migration data...');

        try {
            Schema::disableForeignKeyConstraints();

            $this->info('Truncating item_tag...');
            DB::table('item_tag')->truncate();

            $this->info('Truncating items...');
            DB::table('items')->truncate();

            $this->info('Truncating item_groups...');
            DB::table('item_groups')->truncate();

            $this->info('Truncating tags...');
            DB::table('tags')->truncate();

            Schema::enableForeignKeyConstraints();
            $this->info('Legacy items migration data has been reset successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Reset failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
