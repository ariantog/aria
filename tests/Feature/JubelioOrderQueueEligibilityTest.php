<?php

use App\Services\Jubelio\JubelioOrderQueueEligibility;

beforeEach(function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);
});

it('treats shipped internal with completed channel as eligible for queue', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'channel_status' => 'COMPLETED',
        'transaction_date' => now()->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeTrue();
});

it('allows shipped rows within the max age window', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'transaction_date' => now()->subDays(5)->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeTrue();
});

it('rejects shipped rows older than the max age window', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'transaction_date' => now()->subDays(45)->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeFalse();
});

it('rejects rows with missing transaction date when max age is enforced', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'is_canceled' => 'N',
    ]))->toBeFalse();
});

it('allows completed internal status when transaction date is recent', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'COMPLETED',
        'channel_status' => 'COMPLETED',
        'transaction_date' => now()->subDay()->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeTrue();
});

it('allows returned status within the max age window', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'RETURNED',
        'transaction_date' => now()->subDays(3)->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeTrue();
});

it('rejects canceled shipped rows', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleListRow([
        'internal_status' => 'SHIPPED',
        'transaction_date' => now()->subDay()->toIso8601String(),
        'is_canceled' => 'Y',
    ]))->toBeFalse();
});

it('uses webhook status field when internal_status is absent', function () {
    $eligibility = new JubelioOrderQueueEligibility;

    expect($eligibility->isEligibleApiOrder([
        'status' => 'SHIPPED',
        'transaction_date' => now()->subDay()->toIso8601String(),
        'is_canceled' => 'N',
    ]))->toBeTrue();
});
