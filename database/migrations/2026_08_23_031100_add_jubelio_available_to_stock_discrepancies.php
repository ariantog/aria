<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelio_stock_discrepancies')) {
            return;
        }

        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_available')) {
                $table->decimal('jubelio_available', 15, 2)->nullable()->after('jubelio_on_order');
            }
            if (! Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_reserved')) {
                $table->decimal('jubelio_reserved', 15, 2)->nullable()->after('jubelio_available');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelio_stock_discrepancies')) {
            return;
        }

        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            $columns = array_filter(
                ['jubelio_available', 'jubelio_reserved'],
                fn (string $column) => Schema::hasColumn('jubelio_stock_discrepancies', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
