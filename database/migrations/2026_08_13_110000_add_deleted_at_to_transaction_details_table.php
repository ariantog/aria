<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L12 TransactionDetail uses SoftDeletes; production L10 table has no deleted_at.
 *
 * Standalone migration for prod DBs where align/bootstrap ran before this column was added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_details') || Schema::hasColumn('transaction_details', 'deleted_at')) {
            return;
        }

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_details') || ! Schema::hasColumn('transaction_details', 'deleted_at')) {
            return;
        }

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
