<?php

use App\Enums\AddrbookType;

test('AddrbookType has 9 cases', function () {
    expect(count(AddrbookType::cases()))->toBe(9);
});

test('AddrbookType label returns correct string', function () {
    expect(AddrbookType::Customer->label())->toBe('Customer');
    expect(AddrbookType::Warehouse->label())->toBe('Warehouse');
    expect(AddrbookType::Bank->label())->toBe('Bank (Account)');
    expect(AddrbookType::VirtualWarehouse->label())->toBe('V.Warehouse');
});

test('AddrbookType allowsNegativeStock only for virtual warehouse', function () {
    expect(AddrbookType::VirtualWarehouse->allowsNegativeStock())->toBeTrue();
    expect(AddrbookType::Warehouse->allowsNegativeStock())->toBeFalse();
    expect(AddrbookType::Customer->allowsNegativeStock())->toBeFalse();
});

test('AddrbookType isWarehouse identifies warehouse types', function () {
    expect(AddrbookType::Warehouse->isWarehouse())->toBeTrue();
    expect(AddrbookType::VirtualWarehouse->isWarehouse())->toBeTrue();
    expect(AddrbookType::Customer->isWarehouse())->toBeFalse();
});

test('AddrbookType isFinancial identifies bank/account types', function () {
    expect(AddrbookType::Bank->isFinancial())->toBeTrue();
    expect(AddrbookType::Account->isFinancial())->toBeTrue();
    expect(AddrbookType::Customer->isFinancial())->toBeFalse();
});
