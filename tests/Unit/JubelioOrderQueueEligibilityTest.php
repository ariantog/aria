<?php

use App\Services\Jubelio\JubelioOrderQueueEligibility;

it('treats channel_status completed as ineligible for queue', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'channel_status' => 'COMPLETED',
        'transaction_date' => now()->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeFalse();
});
