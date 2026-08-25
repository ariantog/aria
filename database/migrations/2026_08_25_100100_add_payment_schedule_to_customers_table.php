<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expected payment day-of-month + grace days for faktur payment tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'payment_due_day')) {
                $table->unsignedTinyInteger('payment_due_day')->nullable()->after('npwp');
            }
            if (! Schema::hasColumn('customers', 'payment_grace_days')) {
                $table->unsignedTinyInteger('payment_grace_days')->default(7)->after('payment_due_day');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'payment_grace_days')) {
                $table->dropColumn('payment_grace_days');
            }
            if (Schema::hasColumn('customers', 'payment_due_day')) {
                $table->dropColumn('payment_due_day');
            }
        });
    }
};
