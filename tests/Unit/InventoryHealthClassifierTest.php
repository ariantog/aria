<?php

use App\Services\InventoryHealth\InventoryHealthClassifier;

it('does not treat any 30-day sale as healthy when cover is too low', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 2,
        netPeriod: 20,
        netExtended: 20,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::LOW)
        ->and($status['days_of_cover'])->toBe(3.0);
});

it('marks selling items with 14-90 days of cover as healthy', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 30,
        netPeriod: 30,
        netExtended: 30,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::HEALTHY)
        ->and($status['days_of_cover'])->toBe(30.0);
});

it('marks high-cover sellers as overstock instead of healthy', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 200,
        netPeriod: 10,
        netExtended: 10,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::OVERSTOCK)
        ->and($status['days_of_cover'])->toBe(600.0);
});

it('marks stock with no net sales in 90 days as dead', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 8,
        netPeriod: 0,
        netExtended: 0,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::DEAD);
});

it('marks stock with older-but-not-recent sales as slow moving', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 8,
        netPeriod: 0,
        netExtended: 4,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::SLOW);
});

it('treats zero stock with recent sales as low stock, not healthy', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 0,
        netPeriod: 5,
        netExtended: 5,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::LOW);
});

it('nets negative activity to zero before classifying', function () {
    $status = InventoryHealthClassifier::classify(
        stock: 4,
        netPeriod: -3,
        netExtended: -3,
        periodDays: 30,
    );

    expect($status['key'])->toBe(InventoryHealthClassifier::DEAD);
});
