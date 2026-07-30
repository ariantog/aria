<?php

use App\Enums\TransactionType;

test('TransactionType has correct number of cases', function () {
    expect(count(TransactionType::cases()))->toBe(12);
});

test('TransactionType label returns correct string', function () {
    expect(TransactionType::Buy->label())->toBe('Buy');
    expect(TransactionType::Sell->label())->toBe('Sell');
    expect(TransactionType::CashIn->label())->toBe('Cash In');
    expect(TransactionType::ReturnSupplier->label())->toBe('Ret. Supplier');
});

test('TransactionType isNegative identifies negative types', function () {
    expect(TransactionType::Sell->isNegative())->toBeTrue();
    expect(TransactionType::ReturnSupplier->isNegative())->toBeTrue();
    expect(TransactionType::CashOut->isNegative())->toBeTrue();
    expect(TransactionType::Buy->isNegative())->toBeFalse();
    expect(TransactionType::CashIn->isNegative())->toBeFalse();
});

test('TransactionType hasItems identifies stock-moving types', function () {
    expect(TransactionType::Buy->hasItems())->toBeTrue();
    expect(TransactionType::Sell->hasItems())->toBeTrue();
    expect(TransactionType::Move->hasItems())->toBeTrue();
    expect(TransactionType::CashIn->hasItems())->toBeFalse();
});

test('TransactionType priceSource returns cost for buy types', function () {
    expect(TransactionType::Buy->priceSource())->toBe('cost');
    expect(TransactionType::ReturnSupplier->priceSource())->toBe('cost');
    expect(TransactionType::Sell->priceSource())->toBe('price');
});

test('TransactionType from value round-trips correctly', function () {
    expect(TransactionType::from(1))->toBe(TransactionType::Buy);
    expect(TransactionType::from(2))->toBe(TransactionType::Sell);
    expect(TransactionType::from(9))->toBe(TransactionType::CashIn);
});
