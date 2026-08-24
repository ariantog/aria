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
            if (! Schema::hasColumn('standalone_invoices', 'dp_amount')) {
                $table->decimal('dp_amount', 16, 2)->nullable()->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('standalone_invoices')) {
            return;
        }

        Schema::table('standalone_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('standalone_invoices', 'dp_amount')) {
                $table->dropColumn('dp_amount');
            }
        });
    }
};
