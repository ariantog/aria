<?php

use App\Services\Reporting\ReportingPeriod;

it('keeps display month ranges as calendar dates', function () {
    expect(ReportingPeriod::monthRange(2026, 8))->toBe(['2026-08-01', '2026-08-31']);
});

it('includes midnight on the last calendar day in SQL bounds', function () {
    expect(ReportingPeriod::queryBounds('2026-08-01', '2026-08-31'))
        ->toBe(['2026-08-01', '2026-08-31 23:59:59']);

    expect(ReportingPeriod::monthQueryRange(2026, 8))
        ->toBe(['2026-08-01', '2026-08-31 23:59:59']);

    expect(ReportingPeriod::queryEnd('2026-08-31'))->toBe('2026-08-31 23:59:59');
});
