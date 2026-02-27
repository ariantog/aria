<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('addrbook_dailies')
            ->update([
                'type' => DB::table('addrbooks')
                    ->whereColumn('addrbooks.id', 'addrbook_dailies.addrbook_id')
                    ->select('type'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse data sync
    }
};
