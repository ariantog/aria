<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInsightMonth extends Model
{
    /** Stored on {@see $month} for full-calendar-year rollups (not a calendar month). */
    public const MONTH_YEARLY = 0;

    protected $fillable = [
        'year',
        'month',
        'row_count',
        'months_included',
        'calculated_by',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'row_count' => 'integer',
            'months_included' => 'array',
            'calculated_by' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function isYearly(): bool
    {
        return (int) $this->month === self::MONTH_YEARLY;
    }

    public function periodLabel(): string
    {
        if ($this->isYearly()) {
            return sprintf('%04d', $this->year);
        }

        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /**
     * @return list<int>
     */
    public function monthsIncludedList(): array
    {
        $raw = $this->months_included;
        if (! is_array($raw)) {
            return $this->isYearly() ? [] : [(int) $this->month];
        }

        return array_values(array_unique(array_map('intval', $raw)));
    }
}
