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
            $table->date('date')->nullable()->after('transaction_id');
            $table->unsignedTinyInteger('transaction_type')->nullable()->after('date');
            $table->unsignedBigInteger('sender_id')->nullable()->after('transaction_type');
            $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');

            $table->index(['item_id', 'sender_id', 'transaction_type', 'date'], 'td_audit_index');
            $table->index('date');
        });

        // Backfill data from transactions table
        DB::statement('
            UPDATE transaction_details td 
            JOIN transactions t ON td.transaction_id = t.id 
            SET td.date = t.date, 
                td.transaction_type = t.type, 
                td.sender_id = t.sender_id, 
                td.receiver_id = t.receiver_id
        ');
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
