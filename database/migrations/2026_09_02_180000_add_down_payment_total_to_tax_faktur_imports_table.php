<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang muka deducted from the faktur payable total.
 *
 *   php artisan migrate --path=database/migrations/2026_09_02_180000_add_down_payment_total_to_tax_faktur_imports_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_faktur_imports', 'down_payment_total')) {
                $table->decimal('down_payment_total', 15, 2)->default(0)->after('discount_total');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (Schema::hasColumn('tax_faktur_imports', 'down_payment_total')) {
                $table->dropColumn('down_payment_total');
            }
        });
    }
};
