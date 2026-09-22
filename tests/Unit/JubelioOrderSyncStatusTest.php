<?php

use App\Services\Jubelio\JubelioOrderSyncStatus;

it('labels payload sync errors distinctly for sell and return', function () {
    expect(JubelioOrderSyncStatus::badgeLabel(1, JubelioOrderSyncStatus::ERROR_PAYLOAD, 'SELL'))
        ->toBe('API failed')
        ->and(JubelioOrderSyncStatus::badgeLabel(1, JubelioOrderSyncStatus::ERROR_PAYLOAD, 'RETURN'))
        ->toBe('API failed')
        ->and(JubelioOrderSyncStatus::badgeLabel(
            1,
            JubelioOrderSyncStatus::ERROR_PAYLOAD,
            'RETURN',
            JubelioOrderSyncStatus::MESSAGE_RETURN_SELL_MISSING,
        ))
        ->toBe('Original sale missing');
});

it('detects return missing source sale from stored error text', function () {
    expect(JubelioOrderSyncStatus::isReturnMissingSourceSaleError(JubelioOrderSyncStatus::MESSAGE_RETURN_SELL_MISSING))
        ->toBeTrue()
        ->and(JubelioOrderSyncStatus::isReturnMissingSourceSaleError(JubelioOrderSyncStatus::MESSAGE_SELL_API_EMPTY))
        ->toBeFalse();
});
