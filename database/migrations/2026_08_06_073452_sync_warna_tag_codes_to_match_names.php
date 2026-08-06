<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Warna tags: code mirrors name (uppercase) for consistent SKU/name generation.
        DB::table('tags')
            ->where('type', 20)
            ->update(['code' => DB::raw('UPPER(name)')]);
    }

    public function down(): void
    {
        // Irreversible — previous codes are not preserved.
    }
};
