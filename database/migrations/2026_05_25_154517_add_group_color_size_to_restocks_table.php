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
        Schema::table('restocks', function (Blueprint $table) {
            $table->bigInteger('item_id')->unsigned()->nullable()->change();

            $table->unsignedBigInteger('group_id')->nullable()->after('item_id');
            $table->unsignedBigInteger('color_id')->nullable()->after('group_id');
            $table->string('size_id')->nullable()->after('color_id'); // Can be 'all' or Tag ID

            $table->foreign('group_id')->references('id')->on('item_groups')->onDelete('cascade');
            $table->foreign('color_id')->references('id')->on('tags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restocks', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['color_id']);

            $table->dropColumn(['group_id', 'color_id', 'size_id']);
            $table->bigInteger('item_id')->unsigned()->nullable(false)->change();
        });
    }
};
