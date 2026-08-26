<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_ads_settings') || ! Schema::hasColumn('shopee_ads_settings', 'status')) {
            return;
        }

        DB::table('shopee_ads_settings')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['running'])
            ->update(['status' => 'active']);

        DB::table('shopee_ads_settings')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['paused'])
            ->update(['status' => 'paused']);
    }

    public function down(): void
    {
        // Non-reversible — legacy values were ambiguous in production.
    }
};
