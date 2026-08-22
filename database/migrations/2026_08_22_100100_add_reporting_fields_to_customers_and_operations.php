<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (! Schema::hasColumn('operations', 'report_slug')) {
                $table->string('report_slug', 40)->nullable()->after('description');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'npwp')) {
                $table->string('npwp', 20)->nullable()->after('ppn');
            }
            if (! Schema::hasColumn('customers', 'default_bank_id')) {
                $table->unsignedBigInteger('default_bank_id')->nullable()->after('operation_id');
                $table->foreign('default_bank_id')->references('id')->on('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'reporting_role')) {
                $table->string('reporting_role', 30)->nullable()->after('default_bank_id');
            }
            if (! Schema::hasColumn('customers', 'is_internal_lending')) {
                $table->boolean('is_internal_lending')->default(false)->after('reporting_role');
            }
            if (! Schema::hasColumn('customers', 'is_active_in_reports')) {
                $table->boolean('is_active_in_reports')->default(true)->after('is_internal_lending');
            }
            if (! Schema::hasColumn('customers', 'ledger_hint')) {
                $table->text('ledger_hint')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'default_bank_id')) {
                $table->dropForeign(['default_bank_id']);
            }
            foreach (['npwp', 'default_bank_id', 'reporting_role', 'is_internal_lending', 'is_active_in_reports', 'ledger_hint'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('operations', function (Blueprint $table) {
            if (Schema::hasColumn('operations', 'report_slug')) {
                $table->dropColumn('report_slug');
            }
        });
    }
};
