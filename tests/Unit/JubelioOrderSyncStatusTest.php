<?php

use App\Services\Jubelio\JubelioOrderSyncStatus;

it('labels payload sync errors distinctly for sell and return', function () {
    expect(JubelioOrderSyncStatus::badgeLabel(1, JubelioOrderSyncStatus::ERROR_PAYLOAD, 'SELL'))
        ->toBe('API gagal')
        ->and(JubelioOrderSyncStatus::badgeLabel(1, JubelioOrderSyncStatus::ERROR_PAYLOAD, 'RETURN'))
        ->toBe('Jual asal kosong');
});
