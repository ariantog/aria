<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/support/omnichannel_settings_table.php';

/**
 * Fresh SQLite / greenfield: creates omnichannel_settings when absent.
 *
 * Production drift: if this migration is already recorded in `migrations` but the
 * physical table is missing, run
 * `2026_11_23_120000_install_omnichannel_settings_table` with `--path` instead
 * (guarded CREATE — safe to re-run).
 */
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