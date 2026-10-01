<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One borongan header per penjahit + date range (from/to).
 *
 * If duplicate rows already exist in prod_borongan, this migration skips adding the
 * index until a maintainer merges/deletes duplicates — application code still blocks
 * new duplicates via upsert + unique retry.
 */
return new class extends Migration
{
    private const INDEX = 'prod_borongan_jahit_range_uq';

    public function up(): void
    {
        if (! Schema::hasTable('prod_borongan')) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        if ($this->hasDuplicateRanges()) {
            return;
        }

        Schema::table('prod_borongan', function (Blueprint $table) {
            $table->unique(['jahit_id', 'from', 'to'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prod_borongan') || ! $this->indexExists()) {
            return;
        }

        Schema::table('prod_borongan', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('prod_borongan')");
            foreach ($indexes as $index) {
                if (($index->name ?? '') === self::INDEX) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, 'prod_borongan', self::INDEX]
        );

        return $row !== null;
    }

    private function hasDuplicateRanges(): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS duplicate_groups FROM (
                SELECT jahit_id, `from`, `to`
                FROM prod_borongan
                WHERE `from` IS NOT NULL AND `to` IS NOT NULL
                GROUP BY jahit_id, `from`, `to`
                HAVING COUNT(*) > 1
            ) AS dup'
        );

        return (int) ($row->duplicate_groups ?? 0) > 0;
    }
};
