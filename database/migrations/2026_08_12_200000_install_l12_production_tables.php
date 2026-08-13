<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create L12-only tables on an existing Laravel 10 production database.
 *
 * Every CREATE is guarded with Schema::hasTable(). Safe to run after
 * 2026_08_12_100000_align_production_schema.php on a prod copy.
 *
 * Tables that already exist in production (database/old.sql) are NOT created here:
 * customers, customerstat, customer_class, warehouse_item, item_group, items, tags,
 * transactions, transaction_details, users, prod_*, deleted*, location_customer,
 * locations, settings, jubelio*, stat_sells, warehouse_compares, failed_jobs, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropTablesWithLegacyFkTypeMismatch();
        $this->alignSessionsTable();
        $this->createQueueAndCacheTables();
        $this->createScheduledTasksTable();
        $this->createReportAggregationTables();
        $this->createMonthlyItemSalesTable();
        $this->createStokReportTables();
        $this->createWarehouseItemMonthlyStatsTable();
        $this->createProductPerformanceRollupsTable();
        $this->createWarehouseArrangementTables();
        $this->createItemIdentityConversionTables();
        $this->createRestockTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_cell_histories');
        Schema::dropIfExists('restock_cells');
        Schema::dropIfExists('restock_sheets');
        Schema::dropIfExists('item_identity_conversion_results');
        Schema::dropIfExists('item_identity_conversion_runs');
        Schema::dropIfExists('warehouse_arrangement_candidate_sources');
        Schema::dropIfExists('warehouse_arrangement_candidates');
        Schema::dropIfExists('warehouse_arrangement_pcode_snapshots');
        Schema::dropIfExists('warehouse_arrangement_sources');
        Schema::dropIfExists('product_performance_rollups');
        Schema::dropIfExists('warehouse_item_monthly_stats');
        Schema::dropIfExists('stock_data');
        Schema::dropIfExists('stok_reports');
        Schema::dropIfExists('monthly_item_sales');
        Schema::dropIfExists('daily_inventory_summaries');
        Schema::dropIfExists('monthly_category_summaries');
        Schema::dropIfExists('monthly_account_summaries');
        Schema::dropIfExists('scheduled_tasks');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }

    private function createQueueAndCacheTables(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }
    }

    private function createScheduledTasksTable(): void
    {
        if (Schema::hasTable('scheduled_tasks')) {
            return;
        }

        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('command')->unique();
            $table->string('frequency')->default('daily');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    private function createReportAggregationTables(): void
    {
        if (! Schema::hasTable('monthly_account_summaries')) {
            Schema::create('monthly_account_summaries', function (Blueprint $table) {
                $table->id();
                $table->smallInteger('year');
                $table->tinyInteger('month');
                $table->integer('customer_id');
                $table->decimal('cash_in', 15, 2)->default(0);
                $table->decimal('cash_out', 15, 2)->default(0);
                $table->decimal('sell', 15, 2)->default(0);
                $table->decimal('return', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['year', 'month', 'customer_id'], 'account_summary_unique');
                $table->index(['year', 'month']);
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('monthly_category_summaries')) {
            Schema::create('monthly_category_summaries', function (Blueprint $table) {
                $table->id();
                $table->smallInteger('year');
                $table->tinyInteger('month');
                $table->tinyInteger('addrbook_type');
                $table->decimal('cash_in', 15, 2)->default(0);
                $table->decimal('cash_out', 15, 2)->default(0);
                $table->decimal('sell', 15, 2)->default(0);
                $table->decimal('buy', 15, 2)->default(0);
                $table->decimal('return', 15, 2)->default(0);
                $table->decimal('return_supplier', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['year', 'month', 'addrbook_type'], 'category_summary_unique');
                $table->index(['year', 'month']);
            });
        }

        if (! Schema::hasTable('daily_inventory_summaries')) {
            Schema::create('daily_inventory_summaries', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->integer('warehouse_id');
                $table->integer('item_id');
                $table->decimal('qty_sell', 15, 2)->default(0);
                $table->decimal('qty_buy', 15, 2)->default(0);
                $table->decimal('qty_move_in', 15, 2)->default(0);
                $table->decimal('qty_move_out', 15, 2)->default(0);
                $table->decimal('qty_return_in', 15, 2)->default(0);
                $table->decimal('qty_return_out', 15, 2)->default(0);
                $table->decimal('qty_adjust_in', 15, 2)->default(0);
                $table->decimal('qty_adjust_out', 15, 2)->default(0);
                $table->decimal('stock_on_hand', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['date', 'warehouse_id', 'item_id'], 'inventory_summary_unique');
                $table->index('date');
                $table->index(['warehouse_id', 'item_id']);
                $table->foreign('warehouse_id')->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            });
        }
    }

    private function createMonthlyItemSalesTable(): void
    {
        if (Schema::hasTable('monthly_item_sales')) {
            return;
        }

        Schema::create('monthly_item_sales', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->integer('group_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->decimal('qty_net', 15, 2)->default(0);
            $table->decimal('amount_net', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'group_id', 'customer_id'], 'item_sale_cust_unique');
            $table->index(['year', 'month']);
            $table->foreign('group_id')->references('id')->on('item_group')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    private function createStokReportTables(): void
    {
        if (! Schema::hasTable('stok_reports')) {
            Schema::create('stok_reports', function (Blueprint $table) {
                $table->id();
                $table->timestamp('generet_at');
                $table->string('type');
                $table->integer('generet_by')->nullable();
                $table->timestamps();
                $table->foreign('generet_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('stock_data')) {
            Schema::create('stock_data', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_stock_report')->constrained('stok_reports')->cascadeOnDelete();
                $table->integer('item_id');
                $table->string('item_name');
                $table->decimal('score', 8, 4);
                $table->string('performance_key');
                $table->string('performance_level');
                $table->integer('gap_days')->nullable();
                $table->integer('current_warehouse_id');
                $table->string('current_warehouse_name');
                $table->integer('current_warehouse_qty');
                $table->string('current_warehouse_last_sale')->nullable();
                $table->integer('current_warehouse_days_ago')->nullable();
                $table->integer('best_performing_warehouse_id')->nullable();
                $table->string('best_performing_warehouse_name')->nullable();
                $table->string('best_performing_warehouse_last_sale')->nullable();
                $table->integer('best_performing_warehouse_days_ago')->nullable();
                $table->integer('best_performing_warehouse_qty')->nullable();
                $table->timestamps();
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
                $table->foreign('current_warehouse_id')->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('best_performing_warehouse_id')->references('id')->on('customers')->nullOnDelete();
            });
        }
    }

    private function createWarehouseItemMonthlyStatsTable(): void
    {
        if (Schema::hasTable('warehouse_item_monthly_stats')) {
            return;
        }

        Schema::create('warehouse_item_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('warehouse_id');
            $table->integer('item_id');
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('sold_qty', 15, 2)->default(0);
            $table->decimal('returned_qty', 15, 2)->default(0);
            $table->decimal('sold_value', 15, 2)->default(0);
            $table->decimal('returned_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('item_type')->nullable();
            $table->integer('group_id')->nullable();
            $table->string('pcode', 64)->nullable();
            $table->string('type_code', 64)->default('-');
            $table->string('warna_code', 64)->default('-');
            $table->string('size_code', 64)->default('-');
            $table->unsignedTinyInteger('brand')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id', 'month', 'year'], 'wh_item_monthly_unique');
            $table->index(['warehouse_id', 'year', 'month'], 'wh_item_monthly_wh_period');
            $table->index(['item_id', 'year', 'month'], 'wh_item_monthly_item_period');
            $table->foreign('warehouse_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('item_group')->nullOnDelete();
        });
    }

    private function createProductPerformanceRollupsTable(): void
    {
        if (Schema::hasTable('product_performance_rollups')) {
            return;
        }

        Schema::create('product_performance_rollups', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_days');
            $table->string('lens', 20);
            $table->integer('warehouse_id')->default(0);
            $table->string('grain', 32);
            $table->string('dimension_key', 191);
            $table->unsignedTinyInteger('item_type')->nullable();
            $table->string('label', 255)->nullable();
            $table->decimal('net_qty', 15, 2)->default(0);
            $table->decimal('net_value', 15, 2)->default(0);
            $table->decimal('pct_of_total', 8, 4)->nullable();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['period_days', 'lens', 'warehouse_id', 'grain', 'dimension_key', 'item_type'],
                'product_perf_rollups_unique'
            );
            $table->index(['period_days', 'lens', 'warehouse_id', 'grain', 'rank'], 'product_perf_rollups_lookup');
        });
    }

    private function createWarehouseArrangementTables(): void
    {
        if (! Schema::hasTable('warehouse_arrangement_sources')) {
            Schema::create('warehouse_arrangement_sources', function (Blueprint $table) {
                $table->id();
                $table->integer('destination_warehouse_id');
                $table->integer('source_warehouse_id');
                $table->timestamps();

                $table->unique(['destination_warehouse_id', 'source_warehouse_id'], 'arr_src_dest_src_unique');
                $table->foreign('destination_warehouse_id', 'arr_src_dest_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('source_warehouse_id', 'arr_src_source_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('warehouse_arrangement_pcode_snapshots')) {
            Schema::create('warehouse_arrangement_pcode_snapshots', function (Blueprint $table) {
                $table->id();
                $table->integer('destination_warehouse_id');
                $table->string('pcode');
                $table->string('master')->nullable();
                $table->string('master_name')->nullable();
                $table->string('warna')->nullable();
                $table->unsignedSmallInteger('present_count')->default(0);
                $table->unsignedSmallInteger('total_count')->default(0);
                $table->decimal('completeness_pct', 5, 1)->default(0);
                $table->decimal('family_demand_365', 12, 2)->default(0);
                $table->json('sizes')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['destination_warehouse_id', 'pcode'], 'arr_pcode_dest_pcode_unique');
                $table->foreign('destination_warehouse_id', 'arr_pcode_snap_dest_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('warehouse_arrangement_candidates')) {
            Schema::create('warehouse_arrangement_candidates', function (Blueprint $table) {
                $table->id();
                $table->integer('destination_warehouse_id');
                $table->integer('item_id');
                $table->string('pcode')->nullable();
                $table->string('master')->nullable();
                $table->string('item_code')->nullable();
                $table->string('item_name')->nullable();
                $table->string('size_code')->nullable();
                $table->string('warna')->nullable();
                $table->decimal('demand_30', 12, 2)->default(0);
                $table->decimal('demand_90', 12, 2)->default(0);
                $table->decimal('demand_180', 12, 2)->default(0);
                $table->decimal('demand_365', 12, 2)->default(0);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['destination_warehouse_id', 'item_id'], 'arr_candidate_dest_item_unique');
                $table->index(['destination_warehouse_id', 'pcode'], 'arr_candidate_dest_pcode_idx');
                $table->foreign('destination_warehouse_id', 'arr_cand_dest_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('item_id', 'arr_cand_item_fk')
                    ->references('id')->on('items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('warehouse_arrangement_candidate_sources')) {
            Schema::create('warehouse_arrangement_candidate_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id');
                $table->integer('source_warehouse_id');
                $table->unsignedInteger('source_stock')->default(0);
                $table->unsignedInteger('suggested_qty')->default(1);
                $table->timestamps();

                $table->unique(['candidate_id', 'source_warehouse_id'], 'arr_cand_src_unique');
                $table->foreign('candidate_id', 'arr_cand_src_cand_fk')
                    ->references('id')->on('warehouse_arrangement_candidates')->cascadeOnDelete();
                $table->foreign('source_warehouse_id', 'arr_cand_src_wh_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
            });
        }
    }

    private function createItemIdentityConversionTables(): void
    {
        if (! Schema::hasTable('item_identity_conversion_runs')) {
            Schema::create('item_identity_conversion_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('item_type');
                $table->boolean('dry_run')->default(false);
                $table->unsignedInteger('batch_size')->default(1000);
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->integer('user_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('item_identity_conversion_results')) {
            Schema::create('item_identity_conversion_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->constrained('item_identity_conversion_runs')->cascadeOnDelete();
                $table->integer('item_id');
                $table->string('status', 20);
                $table->string('failure_code', 40)->nullable();
                $table->text('detail')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();

                $table->index(['item_id', 'status']);
                $table->index('failure_code');
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            });
        }
    }

    private function createRestockTables(): void
    {
        if (! Schema::hasTable('restock_sheets')) {
            Schema::create('restock_sheets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('type_tag_id');
                $table->integer('representative_group_id')->nullable();
                $table->integer('created_by');
                $table->timestamp('last_saved_at')->nullable();
                $table->integer('last_saved_by')->nullable();
                $table->timestamps();

                $table->unique('type_tag_id');
                $table->index('type_tag_id');
                $table->foreign('type_tag_id')->references('id')->on('tags')->cascadeOnDelete();
                $table->foreign('representative_group_id')->references('id')->on('item_group')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('last_saved_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('restock_cells')) {
            Schema::create('restock_cells', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restock_sheet_id')->constrained()->cascadeOnDelete();
                $table->integer('item_id');
                $table->integer('color_id')->nullable();
                $table->integer('size_id')->nullable();
                $table->unsignedInteger('qty_restock')->default(0);
                $table->unsignedInteger('qty_production')->default(0);
                $table->unsignedInteger('qty_shipped')->default(0);
                $table->unsignedInteger('qty_missing')->default(0);
                $table->boolean('is_urgent')->default(false);
                $table->boolean('urgent_manual')->default(false);
                $table->timestamp('urgent_flagged_at')->nullable();
                $table->unsignedInteger('urgent_threshold')->nullable();
                $table->timestamps();

                $table->unique(['restock_sheet_id', 'item_id']);
                $table->index(['restock_sheet_id', 'color_id', 'size_id']);
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
                $table->foreign('color_id')->references('id')->on('tags')->nullOnDelete();
                $table->foreign('size_id')->references('id')->on('tags')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('restock_cell_histories')) {
            Schema::create('restock_cell_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restock_cell_id')->constrained()->cascadeOnDelete();
                $table->string('field');
                $table->integer('qty_before')->default(0);
                $table->integer('qty_after')->default(0);
                $table->string('action');
                $table->integer('user_id');
                $table->integer('transaction_id')->nullable();
                $table->string('note')->nullable();
                $table->timestamps();

                $table->index(['restock_cell_id', 'created_at']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            });
        }
    }

    /**
     * L10 sessions table only has id, payload, last_activity.
     */
    private function alignSessionsTable(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('sessions', 'user_id')) {
                $table->integer('user_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('sessions', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('sessions', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    /**
     * A failed first run may leave tables with BIGINT FK columns that cannot reference
     * production INT(11) primary keys. Drop and recreate on retry.
     */
    private function dropTablesWithLegacyFkTypeMismatch(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'monthly_account_summaries' => 'customer_id',
            'daily_inventory_summaries' => 'warehouse_id',
            'monthly_item_sales' => 'customer_id',
            'stok_reports' => 'generet_by',
            'stock_data' => 'item_id',
            'warehouse_item_monthly_stats' => 'warehouse_id',
            'warehouse_arrangement_sources' => 'destination_warehouse_id',
            'warehouse_arrangement_pcode_snapshots' => 'destination_warehouse_id',
            'warehouse_arrangement_candidates' => 'destination_warehouse_id',
            'warehouse_arrangement_candidate_sources' => 'source_warehouse_id',
            'item_identity_conversion_runs' => 'user_id',
            'item_identity_conversion_results' => 'item_id',
            'restock_sheets' => 'type_tag_id',
            'restock_cells' => 'item_id',
            'restock_cell_histories' => 'user_id',
        ];

        foreach ($checks as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $row = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            if ($row && stripos($row->COLUMN_TYPE, 'bigint') !== false) {
                Schema::dropIfExists($table);
            }
        }
    }
};
