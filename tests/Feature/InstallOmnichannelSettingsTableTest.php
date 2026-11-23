<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('installs omnichannel_settings table when absent', function () {
    Schema::dropIfExists('omnichannel_settings');
    DB::table('migrations')
        ->where('migration', '2026_11_23_120000_install_omnichannel_settings_table')
        ->delete();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_11_23_120000_install_omnichannel_settings_table.php',
        '--force' => true,
    ]);

    expect(Schema::hasTable('omnichannel_settings'))->toBeTrue()
        ->and(Schema::hasColumn('omnichannel_settings', 'key'))->toBeTrue()
        ->and(Schema::hasColumn('omnichannel_settings', 'value'))->toBeTrue();
});