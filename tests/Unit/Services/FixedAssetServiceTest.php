<?php

use App\Models\Depreciation;
use App\Services\FixedAssetService;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('computes straight-line monthly amount and expire date', function () {
    $service = new FixedAssetService;
    $row = new Depreciation([
        'buy_price' => 1200000,
        'residual_value' => 0,
        'useful_life_months' => 12,
        'value' => 12,
        'buy_date' => '2026-01-15',
    ]);

    expect($service->monthlyAmount($row))->toBe(100000.0);
    expect($service->expireDate(Carbon::parse('2026-01-15'), 12)->toDateString())->toBe('2026-12-31');
});

it('treats small legacy value as years of useful life', function () {
    $service = new FixedAssetService;
    $row = new Depreciation([
        'buy_price' => 480000,
        'residual_value' => 0,
        'useful_life_months' => 0,
        'value' => 4,
        'buy_date' => '2026-01-01',
        'expire_date' => null,
    ]);

    expect($service->resolveUsefulLifeMonths($row))->toBe(48);
    expect($service->monthlyAmount($row))->toBe(10000.0);
});
