<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_details', 'date')) {
                $table->date('date')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('transaction_details', 'transaction_type')) {
                $table->unsignedTinyInteger('transaction_type')->nullable()->after('date');
            }
            if (!Schema::hasColumn('transaction_details', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('transaction_type');
            }
            if (!Schema::hasColumn('transaction_details', 'receiver_id')) {
                $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
            }
        });

        try {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->index(['item_id', 'sender_id', 'transaction_type', 'date'], 'td_audit_index');
            });
        } catch (\Exception $e) { /* index may already exist */ }
        try {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->index('date');
            });
        } catch (\Exception $e) { /* index may already exist */ }

        // Backfill data from transactions table (SQLite-compatible)
        DB::statement('UPDATE transaction_details SET date = (SELECT date FROM transactions WHERE transactions.id = transaction_details.transaction_id)');
        DB::statement('UPDATE transaction_details SET transaction_type = (SELECT type FROM transactions WHERE transactions.id = transaction_details.transaction_id)');
        DB::statement('UPDATE transaction_details SET sender_id = (SELECT sender_id FROM transactions WHERE transactions.id = transaction_details.transaction_id)');
        DB::statement('UPDATE transaction_details SET receiver_id = (SELECT receiver_id FROM transactions WHERE transactions.id = transaction_details.transaction_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropIndex('td_audit_index');
            $table->dropIndex(['date']);
            $table->dropColumn(['date', 'transaction_type', 'sender_id', 'receiver_id']);
        });
    }
};
