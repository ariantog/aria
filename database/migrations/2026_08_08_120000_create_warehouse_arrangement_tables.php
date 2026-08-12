<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_arrangement_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id');
            $table->foreignId('source_warehouse_id');
            $table->timestamps();

            $table->unique(['destination_warehouse_id', 'source_warehouse_id'], 'arr_src_dest_src_unique');
            $table->foreign('destination_warehouse_id', 'arr_src_dest_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('source_warehouse_id', 'arr_src_source_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('warehouse_arrangement_pcode_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id');
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

        Schema::create('warehouse_arrangement_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id');
            $table->foreignId('item_id');
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

        Schema::create('warehouse_arrangement_candidate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id');
            $table->foreignId('source_warehouse_id');
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

    public function down(): void
    {
        Schema::dropIfExists('warehouse_arrangement_candidate_sources');
        Schema::dropIfExists('warehouse_arrangement_candidates');
        Schema::dropIfExists('warehouse_arrangement_pcode_snapshots');
        Schema::dropIfExists('warehouse_arrangement_sources');
    }
};
