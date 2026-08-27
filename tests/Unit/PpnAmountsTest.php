<?php

use App\Support\PpnAmounts;

it('splits gross amount into dpp and ppn at eleven percent', function () {
    $amounts = PpnAmounts::fromGross(1_110_000, 11);

    expect($amounts['dpp'])->toBe(1_000_000.0)
        ->and($amounts['ppn'])->toBe(110_000.0)
        ->and($amounts['pph'])->toBe(0.0);
});

it('handles rounding so dpp plus ppn equals gross', function () {
    $amounts = PpnAmounts::fromGross(5_000_000, 11);

    expect(round($amounts['dpp'] + $amounts['ppn'], 2))->toBe(5_000_000.0);
});

it('derives citos rental amounts from net payment with pph withholding', function () {
    $amounts = PpnAmounts::fromPayment(17_422_500, withPph: true, ppnRatePercent: 11, pphRatePercent: 10);

    expect($amounts['dpp'])->toBe(17_250_000.0)
        ->and($amounts['ppn'])->toBe(1_897_500.0)
        ->and($amounts['pph'])->toBe(1_725_000.0)
        ->and(round($amounts['dpp'] + $amounts['ppn'] - $amounts['pph'], 2))->toBe(17_422_500.0);
});
