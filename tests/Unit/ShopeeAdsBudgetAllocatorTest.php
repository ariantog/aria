<?php

use App\Services\ShopeeAds\ShopeeAdsBudgetAllocator;

it('adds increment to current budget not starting budget', function () {
    // Bug scenario: starting was 100, user manually raised to 250, bot adds 100 → 350 not 200
    $current = 250;
    $increment = 100;
    $cap = 500000;

    $result = ShopeeAdsBudgetAllocator::addToBudget($current, $increment, $cap);

    expect($result)->toBe(350);
});

it('does not use starting budget when current budget was manually increased', function () {
    $startingBudget = 100;
    $currentOnShopee = 250;
    $increment = 100;

    $wrong = ShopeeAdsBudgetAllocator::addToBudget($startingBudget, $increment, 500000);
    $correct = ShopeeAdsBudgetAllocator::addToBudget($currentOnShopee, $increment, 500000);

    expect($wrong)->toBe(200);
    expect($correct)->toBe(350);
});

it('clamps budget to daily cap', function () {
    expect(ShopeeAdsBudgetAllocator::addToBudget(480000, 50000, 500000))->toBe(500000);
});

it('splits group pool by ROAS tiers', function () {
    $groups = [
        ['campaign_id' => 'g1', 'roas' => 10],
        ['campaign_id' => 'g2', 'roas' => 9],
        ['campaign_id' => 'g3', 'roas' => 7],
        ['campaign_id' => 'g4', 'roas' => 6],
        ['campaign_id' => 'g5', 'roas' => 4],
        ['campaign_id' => 'g6', 'roas' => 3],
    ];

    $allocations = ShopeeAdsBudgetAllocator::splitPoolByRoas(
        $groups,
        1000000,
        60,
        30,
        10,
        500000,
        array_fill_keys(['g1', 'g2', 'g3', 'g4', 'g5', 'g6'], 100000),
    );

    expect($allocations)->toHaveKeys(['g1', 'g2', 'g3', 'g4', 'g5', 'g6']);
    expect(array_sum($allocations))->toBeLessThanOrEqual(1000000);
    expect($allocations['g1'])->toBeGreaterThan($allocations['g6']);
});
