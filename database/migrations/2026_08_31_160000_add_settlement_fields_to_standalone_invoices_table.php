<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('standalone_invoices')) {
            return;
        }

        Schema::table('standalone_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('standalone_invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 16, 2)->default(0)->after('dp_amount');
            }
            if (! Schema::hasColumn('standalone_invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('discount_amount');
            }
            if (! Schema::hasColumn('standalone_invoices', 'paid_by')) {
                $table->unsignedInteger('paid_by')->nullable()->index('si_paid_by_idx')->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('standalone_invoices')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('standalone_invoices', 'paid_by') ? 'paid_by' : null,
            Schema::hasColumn('standalone_invoices', 'paid_at') ? 'paid_at' : null,
            Schema::hasColumn('standalone_invoices', 'discount_amount') ? 'discount_amount' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('standalone_invoices', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
