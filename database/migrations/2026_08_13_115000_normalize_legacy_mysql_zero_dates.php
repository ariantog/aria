<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;

/**
 * Normalize legacy L10 zero dates ('0000-00-00') across the whole database.
 *
 * Must run before any production ALTER (align, defaults, INT fix). Any ALTER on a
 * table that still contains zero dates fails under MySQL strict mode.
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
