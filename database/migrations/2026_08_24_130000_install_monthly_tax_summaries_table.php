<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe install for per-entity monthly tax rollups.
 *
 *   php artisan migrate --path=database/migrations/2026_08_24_130000_install_monthly_tax_summaries_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monthly_tax_summaries')) {
            return;
        }

        Schema::create('monthly_tax_summaries', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
            $table->decimal('ppn_keluaran_dpp', 15, 2)->default(0);
            $table->decimal('ppn_keluaran_tax', 15, 2)->default(0);
            $table->decimal('ppn_masukan_dpp', 15, 2)->default(0);
            $table->decimal('ppn_masukan_tax', 15, 2)->default(0);
            $table->decimal('retur_keluaran_dpp', 15, 2)->default(0);
            $table->decimal('retur_keluaran_tax', 15, 2)->default(0);
            $table->decimal('retur_masukan_dpp', 15, 2)->default(0);
            $table->decimal('retur_masukan_tax', 15, 2)->default(0);
            $table->decimal('pph_final', 15, 2)->default(0);
            $table->decimal('tax_paid', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'reporting_entity_id'], 'monthly_tax_summaries_unique');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_tax_summaries');
    }
};
