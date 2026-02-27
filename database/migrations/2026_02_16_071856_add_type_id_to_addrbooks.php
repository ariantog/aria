<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('addrbooks', function (Blueprint $table) {
            $table->foreignId('addrbook_type_id')->nullable()->after('type')->constrained('addrbook_types')->nullOnDelete();
        });

        // Migrate existing integer types to AddrbookType
        // Mapping based on Addrbook constants
        $types = [
            1 => 'customer',
            2 => 'warehouse',
            3 => 'banks',
            4 => 'supplier',
            5 => 'v-warehouse',
            6 => 'v-account',
            7 => 'reseller',
            // 8 => 'account', // If used
        ];

        foreach ($types as $id => $slug) {
            // Assuming Seeder has run or we insert them here?
            // Safer to insert here to ensure they exist during migration
            $typeId = \DB::table('addrbook_types')->where('slug', $slug)->value('id');
            if (! $typeId) {
                $typeId = \DB::table('addrbook_types')->insertGetId([
                    'name' => ucfirst(str_replace('-', ' ', $slug)),
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \DB::table('addrbooks')->where('type', $id)->update(['addrbook_type_id' => $typeId]);
        }

        Schema::table('addrbooks', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addrbooks', function (Blueprint $table) {
            $table->integer('type')->nullable()->default(1);
        });

        // Reverse migration (optional, complex mapping back)

        Schema::table('addrbooks', function (Blueprint $table) {
            $table->dropForeign(['addrbook_type_id']);
            $table->dropColumn('addrbook_type_id');
        });
    }
};
