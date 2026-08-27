<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *   php artisan migrate --path=database/migrations/2026_08_27_100000_add_sell_transaction_id_to_tax_faktur_imports_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_faktur_imports', 'sell_transaction_id')) {
                $table->unsignedInteger('sell_transaction_id')->nullable()->after('variance_transaction_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (Schema::hasColumn('tax_faktur_imports', 'sell_transaction_id')) {
                $table->dropColumn('sell_transaction_id');
            }
        });
    }
};
