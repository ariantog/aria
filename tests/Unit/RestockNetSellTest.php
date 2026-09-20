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
