<?php

namespace App\Enums;

enum ItemBrand: int
{
    case NO_BRAND = 0;
    case CA = 1;
    case CC = 2;
    case CB = 3;
    case CD = 4;
    case CR = 5;
    case CN = 6;
    case CM = 7;
    case CX = 8;
    case CS = 9;
    case HJ = 10;
    case CP = 11;
    case CJ = 12;
    case PL = 13;
    case DC = 14;
    case CE = 15;
    case CI = 16;
    case CX0 = 17;
    case CX7 = 18;
    case CX8 = 19;
    case CX9 = 20;

    public function label(): string
    {
        return match ($this) {
            self::NO_BRAND => 'No Brand',
            self::CA => 'CA',
            self::CC => 'CC',
            self::CB => 'CB',
            self::CD => 'CD',
            self::CR => 'CR',
            self::CN => 'CN',
            self::CM => 'CM',
            self::CX => 'CX',
            self::CS => 'CS',
            self::HJ => 'HJ',
            self::CP => 'CP',
            self::CJ => 'CJ',
            self::PL => 'PL',
            self::DC => 'DC',
            self::CE => 'CE',
            self::CI => 'CI',
            self::CX0 => 'CX0',
            self::CX7 => 'CX7',
            self::CX8 => 'CX8',
            self::CX9 => 'CX9',
        };
    }
}
