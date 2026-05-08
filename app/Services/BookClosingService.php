<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BookClosingService
{
    /**
     * Get the configured closing day of the month.
     */
    public function getTutupBukuDay(): int
    {
        return (int) Setting::getValue('tutup_buku', 28);
    }

    /**
     * Check if a given date belongs to a month that is already closed.
     */
    public function isDateClosed(Carbon $date): bool
    {
        $tutupBukuDay = $this->getTutupBukuDay();
        $today = Carbon::now();

        // The closing date for the month of the target transaction date.
        // We use min() to handle months with fewer days than the closing day setting.
        $closingDateOfTargetMonth = $date->copy()->day(min($tutupBukuDay, $date->daysInMonth))->startOfDay();

        // If today has passed the closing date of that month, it's considered closed.
        // We compare against the end of the closing day to be precise.
        return $today->startOfDay()->greaterThan($closingDateOfTargetMonth);
    }

    /**
     * Validate a transaction date and throw a ValidationException if it's closed.
     *
     * @throws ValidationException
     */
    public function validateDate(string $date, string $field = 'date'): void
    {
        $carbonDate = Carbon::parse($date);

        if ($this->isDateClosed($carbonDate)) {
            $tutupBukuDay = $this->getTutupBukuDay();
            throw ValidationException::withMessages([
                $field => ["Tanggal sudah melewati batas tutup buku bulan ini (Tanggal {$tutupBukuDay}). Silahkan gunakan tanggal di bulan berikutnya."],
            ]);
        }
    }

    /**
     * Get the minimum allowed date for new transactions.
     */
    public function getMinAllowedDate(): Carbon
    {
        $tutupBukuDay = $this->getTutupBukuDay();
        $today = Carbon::now();

        // Check if current month is already closed
        $closingDateThisMonth = $today->copy()->day(min($tutupBukuDay, $today->daysInMonth))->startOfDay();

        if ($today->startOfDay()->greaterThan($closingDateThisMonth)) {
            // Must be next month
            return $today->copy()->addMonthNoOverflow()->startOfMonth();
        }

        // Can be this month (from the 1st)
        // Note: Historical months are still closed by isDateClosed,
        // but for a "minimum" hint, this month's start is usually what's expected.
        return $today->copy()->startOfMonth();
    }
}
