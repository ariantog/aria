<?php

use App\Services\ItemInsightRestockAlertBuilder;

it('computes days of cover from stock and period net sell rate', function () {
    $builder = app(ItemInsightRestockAlertBuilder::class);

    // 25 units on hand, 40 sold in 30 days → ≈18.75 days cover
    expect($builder->daysOfCover(25, 40, 30))->toBe(18.75);

    // Small cover should not round down to zero
    expect($builder->daysOfCover(2, 10_000, 30))->toBeGreaterThan(0.0);
    expect($builder->daysOfCover(2, 10_000, 30))->toBeLessThan(0.01);
});
