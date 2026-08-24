<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BookClosingService
{
    /**
     * Check if a given date is outside the allowed entry window (current + previous month only).
     */
    public function isDateClosed(Carbon $date): bool
    {
        return $date->copy()->startOfDay()->lessThan($this->getMinAllowedDate());
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
            $minDate = $this->getMinAllowedDate();

            throw ValidationException::withMessages([
                $field => [
                    'Tanggal transaksi hanya boleh di bulan ini atau bulan lalu '
                    .'(mulai '.$minDate->translatedFormat('d M Y').').',
                ],
            ]);
        }
    }

    /**
     * Summary for dashboard / UI reminders about the current closing window.
     *
     * @return array{
     *     closing_day: int,
     *     current_month_closed: bool,
     *     closing_date: Carbon,
     *     days_until_closing: int,
     *     min_allowed_date: Carbon
     * }
     */
    public function getClosingReminder(): array
    {
        $today = Carbon::now()->startOfDay();
        $closingDate = $today->copy()->endOfMonth()->startOfDay();
        $daysUntilClosing = max(0, (int) $today->diffInDays($closingDate, false));

        return [
            'closing_day' => $closingDate->day,
            'current_month_closed' => false,
            'closing_date' => $closingDate,
            'days_until_closing' => $daysUntilClosing,
            'min_allowed_date' => $this->getMinAllowedDate(),
        ];
    }

    /**
     * Earliest date allowed for new transactions (first day of previous month).
     */
    public function getMinAllowedDate(): Carbon
    {
        return Carbon::now()->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
    }
}
