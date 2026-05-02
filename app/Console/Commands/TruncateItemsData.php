<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TruncateItemsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:truncate-items {--force : Skip confirmation} {--generate : Run migration after truncate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely truncate all item-related tables (tags, item_groups, items, item_tag)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to truncate all item tables? This action cannot be undone.')) {
            $this->info('Truncate cancelled.');

            return Command::SUCCESS;
        }

        $this->warn('Starting truncate of Item data...');

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
            $this->info('All item-related tables have been truncated successfully!');

            if ($this->option('generate')) {
                $this->info('Starting regeneration...');
                $this->call('app:migrate-legacy-items');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Truncate failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
