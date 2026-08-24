<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe install for reporting aggregate tables.
 *
 *   php artisan migrate --path=database/migrations/2026_08_24_120000_install_reporting_summary_tables.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->installReportingEntityMonthlySummariesTable();
        $this->installReportingOperationMonthlySummariesTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_operation_monthly_summaries');
        Schema::dropIfExists('reporting_entity_monthly_summaries');
    }

    private function installReportingEntityMonthlySummariesTable(): void
    {
        if (Schema::hasTable('reporting_entity_monthly_summaries')) {
            return;
        }

        Schema::create('reporting_entity_monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'reporting_entity_id'], 'reporting_entity_monthly_unique');
            $table->index(['year', 'month']);
        });
    }

    private function installReportingOperationMonthlySummariesTable(): void
    {
        if (Schema::hasTable('reporting_operation_monthly_summaries')) {
            return;
        }

        Schema::create('reporting_operation_monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('report_slug', 40);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'report_slug'], 'reporting_operation_monthly_unique');
            $table->index(['year', 'month']);
        });
    }
};
