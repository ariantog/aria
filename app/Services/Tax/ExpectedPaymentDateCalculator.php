<?php

namespace App\Services\Tax;

use Illuminate\Support\Carbon;

class ExpectedPaymentDateCalculator
{
    /**
     * Next calendar payment date: given day-of-month in the month after the faktur date.
     * E.g. faktur 31 Juli + due day 15 → 15 Agustus; due day 6 → 6 Agustus.
     */
    public function fromFakturDate(Carbon $fakturDate, ?int $dueDay): ?Carbon
    {
        if ($dueDay === null || $dueDay < 1 || $dueDay > 31) {
            return null;
        }

        $targetMonth = $fakturDate->copy()->startOfMonth()->addMonth();
        $day = min($dueDay, $targetMonth->daysInMonth);

        return $targetMonth->day($day);
    }

    public function isOverdue(
        ?Carbon $expectedPaymentDate,
        ?Carbon $paymentReceivedDate,
        int $graceDays = 7,
    ): bool {
        if ($paymentReceivedDate !== null || $expectedPaymentDate === null) {
            return false;
        }

        $graceDays = max(0, $graceDays);
        $deadline = $expectedPaymentDate->copy()->startOfDay()->addDays($graceDays);

        return now()->startOfDay()->greaterThan($deadline);
    }
}
