<?php

use Illuminate\Support\Facades\Schema;

it('creates warehouse_item without foreign keys after addrbook exists', function () {
    expect(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('items'))->toBeTrue()
        ->and(Schema::hasTable('warehouse_item'))->toBeTrue();
});
