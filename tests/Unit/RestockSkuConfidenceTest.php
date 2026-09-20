<?php

use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\Restock\RestockSkuConfidenceService;

beforeEach(function () {
    $this->service = app(RestockSkuConfidenceService::class);
});

it('classifies steady monthly demand as stable replenishment', function () {
    $months = array_fill(0, 12, 10.0);

    $result = $this->service->classify(
        $months,
        8.0,
        30,
        5.0,
        18.0,
        InventoryHealthClassifier::LOW,
        null,
        0.0,
    );

    expect($result['pattern'])->toBe(RestockSkuConfidenceService::PATTERN_STABLE)
        ->and($result['confidence'])->toBe(RestockSkuConfidenceService::CONFIDENCE_HIGH);
});

it('classifies demand acceleration as spike opportunity', function () {
    $months = array_fill(0, 12, 1.0);

    $result = $this->service->classify(
        $months,
        60.0,
        30,
        2.0,
        1.0,
        InventoryHealthClassifier::LOW,
        null,
        0.0,
    );

    expect($result['pattern'])->toBe(RestockSkuConfidenceService::PATTERN_SPIKE);
});

it('classifies fading demand after a peak as fatigue hold', function () {
    $months = [20, 18, 16, 14, 12, 10, 8, 6, 4, 2, 1, 1];

    $result = $this->service->classify(
        $months,
        2.0,
        30,
        40.0,
        600.0,
        InventoryHealthClassifier::HEALTHY,
        null,
        0.0,
    );

    expect($result['pattern'])->toBe(RestockSkuConfidenceService::PATTERN_FATIGUE)
        ->and($result['confidence'])->toBe(RestockSkuConfidenceService::CONFIDENCE_LOW);
});

it('classifies slow sell-through after last buy as fatigue hold', function () {
    $months = array_merge(array_fill(0, 9, 15.0), [12.0, 10.0, 8.0]);

    $result = $this->service->classify(
        $months,
        30.0,
        30,
        80.0,
        80.0,
        InventoryHealthClassifier::HEALTHY,
        ['qty' => 100.0, 'date' => '2026-01-01'],
        20.0,
        \Illuminate\Support\Carbon::parse('2026-08-01'),
    );

    expect($result['pattern'])->toBe(RestockSkuConfidenceService::PATTERN_FATIGUE);
});
