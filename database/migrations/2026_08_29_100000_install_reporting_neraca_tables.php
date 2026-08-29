<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe install for Phase 2 neraca / persediaan tables.
 *
 *   php artisan migrate --path=database/migrations/2026_08_29_100000_install_reporting_neraca_tables.php --force
 *
 * New L12 tables only (not in old.sql). Guarded CREATE. No DROP of production tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->installMonthlyInventoryValuesTable();
        $this->installBalanceSnapshotsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_balance_snapshots');
        Schema::dropIfExists('reporting_monthly_inventory_values');
    }

    private function installMonthlyInventoryValuesTable(): void
    {
        if (Schema::hasTable('reporting_monthly_inventory_values')) {
            return;
        }

        Schema::create('reporting_monthly_inventory_values', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('material_purchases', 15, 2)->default(0);
            $table->decimal('material_cash_out', 15, 2)->default(0);
            $table->decimal('production_cost', 15, 2)->default(0);
            $table->decimal('cogs', 15, 2)->default(0);
            $table->decimal('adjustment', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month'], 'rep_inv_year_month_uidx');
        });
    }

    private function installBalanceSnapshotsTable(): void
    {
        if (Schema::hasTable('reporting_balance_snapshots')) {
            return;
        }

        Schema::create('reporting_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('as_of_date');
            $table->integer('customer_id');
            $table->unsignedTinyInteger('customer_type')->default(0);
            $table->foreignId('reporting_entity_id')->nullable()->constrained('reporting_entities')->nullOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['as_of_date', 'customer_id'], 'rep_bal_snap_date_cid_uidx');
            $table->index('as_of_date', 'rep_bal_snap_date_idx');
        });
    }
};
