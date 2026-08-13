<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Single production bootstrap migration for an L10 → L12 database clone.
 *
 * Runs all guarded ALTERs (align) then all guarded CREATEs (install).
 * Safe to run on a fresh prod copy in one step:
 *
 *   php artisan migrate --path=database/migrations/2026_08_13_100000_production_database_bootstrap.php --force
 *
 * See doc/production-schema-diff.md for old.sql vs L12 target schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2026_08_12_100000_align_production_schema.php')->up();
        (require __DIR__.'/2026_08_12_200000_install_l12_production_tables.php')->up();
        (require __DIR__.'/2026_08_13_120000_add_production_not_null_column_defaults.php')->up();
        (require __DIR__.'/2026_08_13_130000_fix_production_bigint_columns_to_int.php')->up();
    }

    public function down(): void
    {
        (require __DIR__.'/2026_08_12_200000_install_l12_production_tables.php')->down();
    }
};
