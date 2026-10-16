<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelioorders', 'source_sell_invoice')) {
                $table->string('source_sell_invoice', 255)->nullable()->default(null)->after('invoice');
                $table->index(['type', 'source_sell_invoice'], 'jub_ord_src_sell_inv_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (Schema::hasColumn('jubelioorders', 'source_sell_invoice')) {
                $table->dropIndex('jub_ord_src_sell_inv_idx');
                $table->dropColumn('source_sell_invoice');
            }
        });
    }
};
