<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // OLD -> NEW
        // 2 (Reseller) -> 7
        // 3 (Supplier) -> 4
        // 4 (Warehouse) -> 2
        // 6 (Account) -> 8
        // 7 (V.Account) -> 6

        \Illuminate\Support\Facades\DB::update('
            UPDATE addrbooks SET type = CASE type 
                WHEN 2 THEN 7 
                WHEN 3 THEN 4 
                WHEN 4 THEN 2 
                WHEN 6 THEN 8 
                WHEN 7 THEN 6 
                ELSE type 
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // NEW -> OLD
        // 7 (Reseller) -> 2
        // 4 (Supplier) -> 3
        // 2 (Warehouse) -> 4
        // 8 (Account) -> 6
        // 6 (V.Account) -> 7

        \Illuminate\Support\Facades\DB::update('
            UPDATE addrbooks SET type = CASE type 
                WHEN 7 THEN 2 
                WHEN 4 THEN 3 
                WHEN 2 THEN 4 
                WHEN 8 THEN 6 
                WHEN 6 THEN 7 
                ELSE type 
            END
        ');
    }
};
