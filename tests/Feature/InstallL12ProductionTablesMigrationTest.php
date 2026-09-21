<?php

use Illuminate\Support\Facades\Schema;

it('keeps stok report tables on greenfield sqlite migrate', function () {
    expect(Schema::hasTable('stok_reports'))->toBeTrue()
        ->and(Schema::hasTable('stock_data'))->toBeTrue();
});

it('creates report aggregation tables from install_l12 on greenfield sqlite migrate', function () {
    expect(Schema::hasTable('monthly_account_summaries'))->toBeTrue()
        ->and(Schema::hasTable('daily_inventory_summaries'))->toBeTrue()
        ->and(Schema::hasTable('monthly_item_sales'))->toBeTrue();
});
