<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Support\ProductionSchema;

it('uses greenfield table names by default', function () {
    config(['production_schema.enabled' => false]);

    expect(ProductionSchema::table('addrbook'))->toBe('addrbooks')
        ->and(ProductionSchema::table('warehouse_item'))->toBe('warehouse_items')
        ->and((new Addrbook)->getTable())->toBe('addrbooks')
        ->and((new WarehouseItem)->getTable())->toBe('warehouse_items');
});

it('maps to production table names when enabled', function () {
    config(['production_schema.enabled' => true]);

    expect(ProductionSchema::table('addrbook'))->toBe('customers')
        ->and(ProductionSchema::table('warehouse_item'))->toBe('warehouse_item')
        ->and(ProductionSchema::table('produksi'))->toBe('prod_produksi')
        ->and((new Addrbook)->getTable())->toBe('customers')
        ->and((new AddrbookStat)->getKeyName())->toBe('customer_id');
});

it('maps transaction column aliases in production mode', function () {
    config(['production_schema.enabled' => true]);

    $transaction = new Transaction;
    $transaction->invoice_number = 'INV-001';
    $transaction->due_date = '2026-01-15';
    $transaction->tax_amount = 1100;
    $transaction->discount_percent = 5;

    expect($transaction->getAttributes())
        ->toHaveKey('invoice', 'INV-001')
        ->toHaveKey('due', '2026-01-15')
        ->toHaveKey('ppn', 1100)
        ->toHaveKey('discount', 5);
});
