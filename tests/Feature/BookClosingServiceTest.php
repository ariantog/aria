<?php

use App\Services\BookClosingService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(BookClosingService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows dates in the current and previous month only', function () {
    Carbon::setTestNow('2026-03-15');

    expect($this->service->getMinAllowedDate()->toDateString())->toBe('2026-02-01')
        ->and($this->service->isDateClosed(Carbon::parse('2026-03-01')))->toBeFalse()
        ->and($this->service->isDateClosed(Carbon::parse('2026-02-01')))->toBeFalse()
        ->and($this->service->isDateClosed(Carbon::parse('2026-01-31')))->toBeTrue();
});

it('rejects transaction dates older than the previous month', function () {
    Carbon::setTestNow('2026-03-15');

    expect(fn () => $this->service->validateDate('2026-01-15'))
        ->toThrow(ValidationException::class);
});

it('reminds users about end-of-month closing', function () {
    Carbon::setTestNow('2026-03-15');

    $reminder = $this->service->getClosingReminder();

    expect($reminder['closing_date']->toDateString())->toBe('2026-03-31')
        ->and($reminder['days_until_closing'])->toBe(16)
        ->and($reminder['current_month_closed'])->toBeFalse()
        ->and($reminder['min_allowed_date']->toDateString())->toBe('2026-02-01');
});

it('shows zero days until closing on the last day of the month', function () {
    Carbon::setTestNow('2026-03-31');

    $reminder = $this->service->getClosingReminder();

    expect($reminder['days_until_closing'])->toBe(0)
        ->and($reminder['closing_date']->toDateString())->toBe('2026-03-31');
});

it('still allows previous month entry at the start of a new month', function () {
    Carbon::setTestNow('2026-04-01');

    expect($this->service->getMinAllowedDate()->toDateString())->toBe('2026-03-01')
        ->and($this->service->isDateClosed(Carbon::parse('2026-03-31')))->toBeFalse()
        ->and($this->service->isDateClosed(Carbon::parse('2026-02-28')))->toBeTrue();
});
