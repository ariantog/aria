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
            $table->integer('type')->after('member_id')->nullable(); // Nullable initially for migration
            $table->dropForeign(['addrbook_type_id']);
            $table->dropColumn('addrbook_type_id');
        });

        Schema::dropIfExists('addrbook_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('addrbook_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('addrbooks', function (Blueprint $table) {
            $table->foreignId('addrbook_type_id')->nullable()->constrained('addrbook_types');
            $table->dropColumn('type');
        });
    }
};
