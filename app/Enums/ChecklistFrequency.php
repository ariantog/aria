<?php

namespace App\Enums;

enum ChecklistFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Biweekly => 'Dwi Minggu',
            self::Monthly => 'Bulanan',
        };
    }

    public function dashboardLabel(): string
    {
        return match ($this) {
            self::Daily => 'Checklist harian',
            self::Weekly => 'Checklist mingguan',
            self::Biweekly => 'Checklist dwi minggu',
            self::Monthly => 'Checklist bulanan',
        };
    }
}
