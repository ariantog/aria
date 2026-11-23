<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/support/omnichannel_settings_table.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichannel_settings')) {
            return;
        }

        aria_create_omnichannel_settings_table();
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichannel_settings');
    }
};