<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPN/PPh options for variance cash-out (selisih pembayaran faktur).
 *
 *   php artisan migrate --path=database/migrations/2026_10_06_120000_add_variance_tax_fields_to_tax_faktur_imports_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_faktur_imports', 'variance_record_ppn')) {
                $table->boolean('variance_record_ppn')->default(false)->after('variance_expense_addrbook_id');
            }
            if (! Schema::hasColumn('tax_faktur_imports', 'variance_record_pph')) {
                $table->boolean('variance_record_pph')->default(false)->after('variance_record_ppn');
            }
            if (! Schema::hasColumn('tax_faktur_imports', 'variance_ppn_dpp')) {
                $table->decimal('variance_ppn_dpp', 15, 2)->nullable()->after('variance_record_pph');
            }
            if (! Schema::hasColumn('tax_faktur_imports', 'variance_ppn')) {
                $table->decimal('variance_ppn', 15, 2)->nullable()->after('variance_ppn_dpp');
            }
            if (! Schema::hasColumn('tax_faktur_imports', 'variance_pph')) {
                $table->decimal('variance_pph', 15, 2)->nullable()->after('variance_ppn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            foreach (['variance_pph', 'variance_ppn', 'variance_ppn_dpp', 'variance_record_pph', 'variance_record_ppn'] as $column) {
                if (Schema::hasColumn('tax_faktur_imports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
