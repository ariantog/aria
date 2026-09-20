<?php

use App\Services\Restock\RestockNetSell;

it('matches item insights net qty per warehouse stats row', function () {
    expect(RestockNetSell::netQtyFromStatRow(50, 10))->toBe(40.0)
        ->and(RestockNetSell::netQtyFromStatRow(10, 20))->toBe(0.0);
});

it('annualizes health window net to monthly rate', function () {
    expect(RestockNetSell::monthlyRateFromPeriod(40, 30))->toBe(40.0)
        ->and(RestockNetSell::monthlyRateFromPeriod(20, 15))->toBe(40.0);
});

it('assigns velocity tiers with strict greater-than thresholds', function () {
    expect(RestockNetSell::velocityTier(100))->toBe(RestockNetSell::TIER_FAST)
        ->and(RestockNetSell::velocityTier(100.1))->toBe(RestockNetSell::TIER_HERO)
        ->and(RestockNetSell::velocityTier(50))->toBe(RestockNetSell::TIER_MEDIUM)
        ->and(RestockNetSell::velocityTier(50.1))->toBe(RestockNetSell::TIER_FAST)
        ->and(RestockNetSell::velocityTier(30))->toBe(RestockNetSell::TIER_BELOW)
        ->and(RestockNetSell::velocityTier(30.1))->toBe(RestockNetSell::TIER_MEDIUM);
});
