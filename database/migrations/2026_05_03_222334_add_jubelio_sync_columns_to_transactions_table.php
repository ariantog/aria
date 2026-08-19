<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (Schema::hasColumn('transactions', 'jubelio_return')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('sync_hide', 1)->default('N')->after('status');
            $table->bigInteger('a_submit_by')->unsigned()->nullable()->after('sync_hide');
            $table->bigInteger('b_submit_by')->unsigned()->nullable()->after('a_submit_by');
            $table->string('a_reference_id')->nullable()->after('b_submit_by');
            $table->string('b_reference_id')->nullable()->after('a_reference_id');
            $table->integer('submit_a_count')->default(0)->after('b_reference_id');
            $table->integer('submit_b_count')->default(0)->after('submit_a_count');
            $table->integer('jubelio_return')->default(0)->after('submit_b_count');

            $table->foreign('a_submit_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('b_submit_by')->references('id')->on('users')->onDelete('set null');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['a_submit_by']);
            $table->dropForeign(['b_submit_by']);
            $table->dropColumn([
                'sync_hide',
                'a_submit_by',
                'b_submit_by',
                'a_reference_id',
                'b_reference_id',
                'submit_a_count',
                'submit_b_count',
                'jubelio_return',
            ]);
        });
    }
};
