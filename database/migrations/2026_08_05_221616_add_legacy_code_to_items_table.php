<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('legacy_code')->nullable()->after('code');
            $table->index('legacy_code');
        });

        // Preserve pre-migration SKUs for Jubelio + barcode lookups after code format changes.
        DB::table('items')
            ->whereNull('legacy_code')
            ->where('code', '!=', '')
            ->update(['legacy_code' => DB::raw('code')]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['legacy_code']);
            $table->dropColumn('legacy_code');
        });
    }
};
