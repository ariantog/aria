<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;

/**
 * Normalize legacy L10 zero dates ('0000-00-00') across the whole database.
 *
 * Scans every base table for date/datetime/timestamp/year columns, nulls or
 * replaces invalid values, and relaxes column defaults that use zero dates.
 * Must run before any production ALTER (align, defaults, INT fix).
 */
return new class extends Migration
{
    public function up(): void
    {
        ProductionMysqlCompat::normalizeZeroDatesForDatabase();
    }

    public function down(): void
    {
        // Data normalization is kept.
    }
};
