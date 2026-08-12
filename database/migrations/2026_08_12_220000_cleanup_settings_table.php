<?php

use App\Support\SettingRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('group')->nullable()->index();
                $table->string('name');
                $table->string('slug', 100);
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('settings', 'slug')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('slug', 100)->nullable()->after('name');
            });

            DB::statement("UPDATE `settings` SET `slug` = `name` WHERE `slug` IS NULL OR `slug` = ''");
        }

        if (! Schema::hasColumn('settings', 'group')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('group')->nullable()->after('id')->index();
            });
        }

        $this->deduplicateBySlug();
        $this->removeUnmanagedSettings();
        $this->ensureUniqueIndexes();
    }

    public function down(): void
    {
        // Irreversible — deduplication and legacy cleanup are kept.
    }

    private function deduplicateBySlug(): void
    {
        if (! Schema::hasColumn('settings', 'slug')) {
            return;
        }

        $duplicateSlugs = DB::table('settings')
            ->select('slug')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicateSlugs as $slug) {
            $keepId = DB::table('settings')
                ->where('slug', $slug)
                ->orderByDesc('id')
                ->value('id');

            if ($keepId === null) {
                continue;
            }

            DB::table('settings')
                ->where('slug', $slug)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    private function removeUnmanagedSettings(): void
    {
        if (! Schema::hasColumn('settings', 'slug')) {
            return;
        }

        $allowedSlugs = array_merge(
            SettingRegistry::slugs(),
            SettingRegistry::SYSTEM_SLUGS,
        );

        DB::table('settings')
            ->where(function ($query) use ($allowedSlugs) {
                $query->whereIn('slug', SettingRegistry::LEGACY_SLUGS);

                if ($allowedSlugs !== []) {
                    $query->orWhereNotIn('slug', $allowedSlugs);
                }
            })
            ->delete();

        // Drop deprecated invoice text settings — branding now comes from warehouse description.
        DB::table('settings')
            ->whereIn('slug', ['invoice_company_name', 'invoice_address', 'invoice_phone'])
            ->delete();
    }

    private function ensureUniqueIndexes(): void
    {
        if (! Schema::hasColumn('settings', 'slug')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->ensureSqliteUniqueIndex('settings', 'settings_slug_unique', 'slug');
            $this->ensureSqliteUniqueIndex('settings', 'settings_name_unique', 'name');

            return;
        }

        if ($driver === 'mysql') {
            if (! $this->indexExists('settings', 'settings_slug_unique')) {
                DB::statement('ALTER TABLE `settings` ADD UNIQUE INDEX `settings_slug_unique` (`slug`)');
            }

            if (! $this->indexExists('settings', 'settings_name_unique')) {
                DB::statement('ALTER TABLE `settings` ADD UNIQUE INDEX `settings_name_unique` (`name`)');
            }
        }
    }

    private function ensureSqliteUniqueIndex(string $table, string $indexName, string $column): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$column})");
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS total FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $indexName]
            );

            return ($row->total ?? 0) > 0;
        }

        return false;
    }
};
