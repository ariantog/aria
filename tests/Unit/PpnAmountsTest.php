<?php

use App\Support\PpnAmounts;

it('splits gross amount into dpp and ppn at eleven percent', function () {
    $amounts = PpnAmounts::fromGross(1_110_000, 11);

    expect($amounts['dpp'])->toBe(1_000_000.0)
        ->and($amounts['ppn'])->toBe(110_000.0);
});

it('handles rounding so dpp plus ppn equals gross', function () {
    $amounts = PpnAmounts::fromGross(5_000_000, 11);

    expect(round($amounts['dpp'] + $amounts['ppn'], 2))->toBe(5_000_000.0);
});
