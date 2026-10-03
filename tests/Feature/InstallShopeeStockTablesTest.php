<?php

use Illuminate\Support\Facades\Schema;

it('installs shopee stock tables and item link columns', function () {
    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_10_03_100000_install_shopee_stock_tables.php',
    ])->assertSuccessful();

    expect(Schema::hasTable('shopee_syncs'))->toBeTrue()
        ->and(Schema::hasColumn('items', 'shopee_item_id'))->toBeTrue()
        ->and(Schema::hasColumn('items', 'shopee_model_id'))->toBeTrue();
});
