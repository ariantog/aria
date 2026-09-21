<?php

use Illuminate\Support\Facades\Schema;

it('keeps stok report tables on greenfield sqlite migrate', function () {
    expect(Schema::hasTable('stok_reports'))->toBeTrue()
        ->and(Schema::hasTable('stock_data'))->toBeTrue();
});
