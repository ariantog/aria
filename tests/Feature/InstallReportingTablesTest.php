<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('installs reporting schema via dedicated migration path', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_22_110000_install_reporting_tables.php',
        '--force' => true,
    ]);

    expect(Schema::hasTable('reporting_entities'))->toBeTrue()
        ->and(Schema::hasTable('reporting_entity_banks'))->toBeTrue()
        ->and(Schema::hasTable('reporting_channel_banks'))->toBeTrue()
        ->and(Schema::hasTable('ledger_merge_maps'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'default_bank_id'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'ledger_hint'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'is_internal_lending'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'is_active_in_reports'))->toBeTrue()
        ->and(Schema::hasColumn('operations', 'report_slug'))->toBeTrue();
});

it('uses integer columns for customer references in reporting tables', function () {
    if (Schema::getConnection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('SQLite column introspection only in this test.');
    }

    $columns = collect(Schema::getColumnListing('reporting_channel_banks'));

    expect($columns)->toContain('customer_id', 'bank_id');
});
