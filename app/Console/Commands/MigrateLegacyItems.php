<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-items {--truncate : Truncate existing item tables before migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate item groups, tags, items, and item tags from legacy database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting migration of Item data...');

        $legacyDb = DB::connection('core_legacy');

        try {
            Schema::disableForeignKeyConstraints();

            // 1. Clear existing data if flag is provided
            if ($this->option('truncate')) {
                $this->warn('Clearing existing item tables...');
                DB::table('item_tag')->truncate();
                DB::table('items')->truncate();
                DB::table('item_groups')->truncate();
                DB::table('tags')->truncate();
                $this->info('Tables truncated.');
            } else {
                $this->info('Truncate flag not provided. Appending data...');
            }

            // 2. Migrate Item Groups
            $this->info('Migrating Item Groups...');
            $legacyGroups = $legacyDb->table('item_group')->get();
            $barGroups = $this->output->createProgressBar($legacyGroups->count());
            $barGroups->start();
            foreach ($legacyGroups as $group) {
                DB::table('item_groups')->insert([
                    'id' => $group->id,
                    'master' => $group->master,
                    'name' => $group->name,
                    'variant' => $group->variant,
                    'description' => $group->description,
                    'alias' => $group->alias,
                    'description2' => $group->description2,
                ]);
                $barGroups->advance();
            }
            $barGroups->finish();
            $this->newLine();
            $this->info("Migrated {$legacyGroups->count()} item groups.");

            // 3. Migrate Tags
            $this->info('Migrating Tags...');
            $legacyTags = $legacyDb->table('tags')->get();
            $barTags = $this->output->createProgressBar($legacyTags->count());
            $barTags->start();
            foreach ($legacyTags as $tag) {
                DB::table('tags')->insert([
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'code' => $tag->code,
                    'type' => $tag->type,
                    'item_type' => $tag->item_type,
                ]);
                $barTags->advance();
            }
            $barTags->finish();
            $this->newLine();
            $this->info("Migrated {$legacyTags->count()} tags.");

            // 4. Migrate Items
            $this->info('Identifying items to migrate (active since 2020 or created since 2025)...');
            $activeItemIdsFromTransactions = $legacyDb->table('transaction_details')
                ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
                ->whereYear('transactions.date', '>=', 2020)
                ->distinct()
                ->pluck('item_id')
                ->toArray();

            $freshItemIds = $legacyDb->table('items')
                ->whereYear('created_at', '>=', 2025)
                ->pluck('id')
                ->toArray();

            $activeItemIds = array_unique(array_merge($activeItemIdsFromTransactions, $freshItemIds));

            $this->info('Migrating Items...');
            $totalItems = $legacyDb->table('items')
                ->whereIn('id', $activeItemIds)
                ->count();
            $bar = $this->output->createProgressBar($totalItems);
            $bar->start();

            $legacyDb->table('items')
                ->whereIn('id', $activeItemIds)
                ->orderBy('id')
                ->chunk(500, function ($items) use ($bar) {
                    foreach ($items as $item) {
                        DB::table('items')->insert([
                            'id' => $item->id,
                            'group_id' => $item->group_id,
                            'name' => $item->name,
                            'code' => $item->code,
                            'pcode' => $item->pcode,
                            'brand' => $item->brand,
                            'type' => $item->type,
                            'size' => $item->size,
                            'genre' => $item->genre,
                            'price' => $item->price,
                            'cost' => $item->cost,
                            'tag_ids' => $item->tag_ids,
                            'description' => $item->description,
                            'description2' => $item->description2,
                            'jubelio_item_id' => $item->jubelio_item_id,
                            'deleted_at' => $this->validateDate($item->deleted_at),
                            'created_at' => $this->validateDate($item->created_at),
                            'updated_at' => $this->validateDate($item->updated_at),
                        ]);
                        $bar->advance();
                    }
                });

            $bar->finish();
            $this->newLine();
            $this->info("Migrated {$totalItems} items.");

            // 5. Migrate Item Tag (Pivot)
            $this->info('Migrating Item Tag relations...');
            $totalPivot = $legacyDb->table('item_tag')
                ->whereIn('item_id', $activeItemIds)
                ->count();
            $barPivot = $this->output->createProgressBar($totalPivot);
            $barPivot->start();

            $legacyDb->table('item_tag')
                ->whereIn('item_id', $activeItemIds)
                ->orderBy('id')
                ->chunk(1000, function ($pivotRows) use ($barPivot) {
                    foreach ($pivotRows as $row) {
                        DB::table('item_tag')->insert([
                            'id' => $row->id,
                            'item_id' => $row->item_id,
                            'tag_id' => $row->tag_id,
                            'created_at' => $this->validateDate($row->created_at),
                            'updated_at' => $this->validateDate($row->updated_at),
                        ]);
                        $barPivot->advance();
                    }
                });

            $barPivot->finish();
            $this->newLine();

            Schema::enableForeignKeyConstraints();
            $this->info('Migration completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Migration failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Validate and format date.
     */
    private function validateDate(?string $date): ?string
    {
        if (! $date || str_starts_with($date, '-') || str_contains($date, '0000-00-00')) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
