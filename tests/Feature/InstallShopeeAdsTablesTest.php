<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('bootstraps shopee ads schema when base tables are missing', function () {
    Schema::dropIfExists('shopee_ads_item_ads');
    Schema::dropIfExists('shopee_ads_budget_history');
    Schema::dropIfExists('shopee_ads_group_states');
    Schema::dropIfExists('shopee_ads_schedules');
    Schema::dropIfExists('shopee_ads_settings');

    DB::table('migrations')
        ->where('migration', '2026_08_25_100000_bootstrap_shopee_ads_tables')
        ->delete();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100000_bootstrap_shopee_ads_tables.php',
        '--force' => true,
    ]);

    expect(Schema::hasTable('shopee_ads_settings'))->toBeTrue()
        ->and(Schema::hasTable('shopee_ads_schedules'))->toBeTrue()
        ->and(Schema::hasTable('shopee_ads_group_states'))->toBeTrue()
        ->and(Schema::hasTable('shopee_ads_budget_history'))->toBeTrue()
        ->and(Schema::hasTable('shopee_ads_item_ads'))->toBeTrue()
        ->and(Schema::hasColumn('shopee_ads_settings', 'starting_budget_gmv_max'))->toBeTrue()
        ->and(Schema::hasColumn('shopee_ads_settings', 'gms_campaign_id'))->toBeTrue()
        ->and(Schema::hasColumn('shopee_ads_settings', 'item_ads_enabled'))->toBeTrue();
});

it('is safe to re-run when shopee ads tables already exist', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_25_100000_bootstrap_shopee_ads_tables.php',
        '--force' => true,
    ]);

    expect(Schema::hasTable('shopee_ads_settings'))->toBeTrue()
        ->and(Schema::hasColumn('shopee_ads_settings', 'gms_current_budget'))->toBeTrue();
});
