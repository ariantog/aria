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
        Schema::table('locations', function (Blueprint $table) {
            // Drop index on member_id if it exists (it was added in a previous migration)
            try {
                $table->dropIndex(['member_id']);
            } catch (\Exception $e) {
                // Index might not exist or verify failed, continue to drop column
            }

            $columns = ['ppn', 'is_online', 'member_id', 'contact_person', 'phone', 'email', 'deleted_at'];
            $table->dropColumn(array_filter($columns, fn ($c) => Schema::hasColumn('locations', $c)));
        });

        Schema::dropIfExists('location_stats');
        Schema::dropIfExists('location_classes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-adding these is complex and effectively handled by previous migrations if rolled back properly.
        // Using strict rollback might require re-defining them here, but for now we leave it empty
        // as this is a "fix forward" migration.
    }
};
