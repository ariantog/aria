<?php

use App\Services\Jubelio\JubelioOrderLineQuantity;

it('falls back from zero qty to qty_picked for sell lines', function () {
    $qty = JubelioOrderLineQuantity::resolve([
        'qty' => '0.0000',
        'qty_in_base' => '1.0000',
        'qty_picked' => '1.0000',
    ], 'qty');

    expect($qty)->toBe(1.0);
});

it('prefers primary qty when positive', function () {
    $qty = JubelioOrderLineQuantity::resolve([
        'qty' => '2.0000',
        'qty_picked' => '1.0000',
    ], 'qty');

    expect($qty)->toBe(2.0);
});

it('uses qty_in_base primary for return lines when qty is zero', function () {
    $qty = JubelioOrderLineQuantity::resolve([
        'qty' => '0.0000',
        'qty_in_base' => '3.0000',
        'qty_picked' => '1.0000',
    ], 'qty_in_base');

    expect($qty)->toBe(3.0);
});
