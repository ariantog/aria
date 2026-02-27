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
            if (! Schema::hasColumn('locations', 'ppn')) {
                $table->boolean('ppn')->default(false)->after('description');
            }
            if (! Schema::hasColumn('locations', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('ppn');
            }
            if (! Schema::hasColumn('locations', 'member_id')) {
                $table->string('member_id')->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('locations', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('member_id');
            }
            if (! Schema::hasColumn('locations', 'phone')) {
                $table->string('phone')->nullable()->after('contact_person');
            }
            if (! Schema::hasColumn('locations', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('locations', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $columns = ['ppn', 'is_online', 'member_id', 'contact_person', 'phone', 'email'];
            $table->dropColumn(array_filter($columns, fn ($c) => Schema::hasColumn('locations', $c)));
            if (Schema::hasColumn('locations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
