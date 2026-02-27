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
            $table->boolean('ppn')->default(false)->after('description');
            $table->boolean('is_online')->default(false)->after('ppn');
            $table->string('member_id')->nullable()->after('is_online')->index();
            $table->string('contact_person')->nullable()->after('member_id');
            $table->string('phone')->nullable()->after('contact_person');
            $table->string('email')->nullable()->after('phone');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['ppn', 'is_online', 'member_id', 'contact_person', 'phone', 'email', 'deleted_at']);
        });
    }
};
