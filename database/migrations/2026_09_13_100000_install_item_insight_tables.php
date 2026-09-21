<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated monthly item insight rankings (best sellers, profit, loss leaders, velocity).
 *
 * Production:
 *   php artisan migrate --path=database/migrations/2026_09_13_100000_install_item_insight_tables.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_insight_months')) {
            Schema::create('item_insight_months', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('calculated_by')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->unique(['year', 'month'], 'item_insight_months_period_unique');
            });
        }

        if (! Schema::hasTable('item_insight_rankings')) {
            Schema::create('item_insight_rankings', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->string('category', 32);
                $table->unsignedInteger('rank')->default(0);
                $table->unsignedInteger('item_id');
                $table->string('item_name', 255)->nullable();
                $table->string('item_code', 191)->nullable();
                $table->decimal('net_qty', 15, 2)->default(0);
                $table->decimal('net_value', 15, 2)->default(0);
                $table->decimal('cost_total', 15, 2)->default(0);
                $table->decimal('profit', 15, 2)->default(0);
                $table->decimal('margin_pct', 8, 4)->nullable();
                $table->decimal('daily_velocity', 12, 4)->default(0);
                $table->timestamps();

                $table->unique(
                    ['year', 'month', 'category', 'rank'],
                    'item_insight_rank_period_cat_rank_uq'
                );
                $table->index(['year', 'month', 'category'], 'item_insight_rank_period_cat_idx');
                $table->index('item_id', 'item_insight_rank_item_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_insight_rankings');
        Schema::dropIfExists('item_insight_months');
    }
};
