<?php

use App\Services\Jubelio\JubelioSellerIncomeResolver;

it('computes seller income from sub_total minus marketplace fees including promo', function () {
    $resolver = new JubelioSellerIncomeResolver();

    $income = $resolver->resolve([
        'sub_total' => 64000,
        'subsidi' => 5000,
        'platform_fee' => 9215,
        'free_shipping_fee' => 3540,
        'service_fee' => 2655,
        'promotion_fee' => 655,
    ], 64000);

    expect($income)->toBe(42935.0);
});

it('falls back to sub_total minus fees without promo when promo fee is missing', function () {
    $resolver = new JubelioSellerIncomeResolver();

    $income = $resolver->resolve([
        'sub_total' => 64000,
        'subsidi' => 5000,
        'platform_fee' => 9215,
        'free_shipping_fee' => 3540,
        'service_fee' => 2655,
    ], 64000);

    expect($income)->toBe(43590.0);
});

it('prefers real_total when it is lower than customer grand_total', function () {
    $resolver = new JubelioSellerIncomeResolver();

    $income = $resolver->resolve([
        'sub_total' => 64000,
        'grand_total' => 64000,
        'real_total' => 42935,
    ], 64000);

    expect($income)->toBe(42935.0);
});

it('falls back to sub_total minus grand_total for list-price marketplace orders', function () {
    $resolver = new JubelioSellerIncomeResolver();

    $income = $resolver->resolve([
        'sub_total' => 122590,
        'grand_total' => 79000,
        'real_total' => 79000,
    ], 122590);

    expect($income)->toBe(43590.0);
});

it('uses customer grand_total when line prices already match payment', function () {
    $resolver = new JubelioSellerIncomeResolver();

    $income = $resolver->resolve([
        'sub_total' => 122590,
        'grand_total' => 79000,
        'real_total' => 79000,
    ], 79000);

    expect($income)->toBe(79000.0);
});
