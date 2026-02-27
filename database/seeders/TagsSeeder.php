<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Source Database: core
        // Destination Database: defined in .env (core-aria / core_aria)

        // We assume they are on the same host/user, so we can access `core.tags`.
        // If not, we might need a separate connection config.

        $sourceDb = 'core';
        $sourceTable = 'tags';

        $this->command->info("Migrating tags from $sourceDb.$sourceTable...");

        try {
            $sourceTags = DB::connection('mysql')->table("$sourceDb.$sourceTable")->get();

            foreach ($sourceTags as $sourceTag) {
                Tag::updateOrCreate(
                    ['id' => $sourceTag->id],
                    [
                        'name' => $sourceTag->name,
                        'code' => $sourceTag->code,
                        'type' => $sourceTag->type,
                        'item_type' => $sourceTag->item_type ?? 0, // Handle missing column if needed
                        // 'created_at' => $sourceTag->created_at,
                        // 'updated_at' => $sourceTag->updated_at,
                    ]
                );
            }

            $this->command->info('Migrated '.$sourceTags->count().' tags.');

        } catch (\Exception $e) {
            $this->command->error('Error migrating tags: '.$e->getMessage());
            $this->command->warn("Please ensure database '$sourceDb' exists and is accessible.");
        }
    }
}
