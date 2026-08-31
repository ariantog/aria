<?php

use App\Services\Jubelio\JubelioAdjustmentHint;

it('suggests a stock fix when jubelio reports insufficient qty', function () {
    expect(JubelioAdjustmentHint::for('Qty exceeds available stock'))
        ->toContain('Stok di lokasi Jubelio tidak cukup');
});

it('suggests linking an item when jubelio cannot find it', function () {
    expect(JubelioAdjustmentHint::for('Item not found'))
        ->toContain('Item → tab Jubelio');
});

it('suggests refreshing the token on auth failure', function () {
    expect(JubelioAdjustmentHint::for('Jubelio auth failed.'))
        ->toContain('Jubelio → Koneksi');
});

it('suggests checking jubelio for an unclear response', function () {
    expect(JubelioAdjustmentHint::for('Respons API Jubelio tidak jelas (tidak ada reference ID).'))
        ->toContain('Penyesuaian Stok');
});

it('falls back to a generic retry hint', function () {
    expect(JubelioAdjustmentHint::for('Something unexpected from the API'))
        ->toContain('push ulang');
});
