<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichannel_post_sell_stats')) {
            return;
        }

        Schema::create('omnichannel_post_sell_stats', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('scope_key', 64);
            $table->unsignedInteger('posted')->default(0);
            $table->unsignedInteger('eligible_to_post')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichannel_post_sell_stats');
    }
};