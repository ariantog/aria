<?php

namespace App\Enums;

enum TransactionType: int
{
    case Buy = 1;
    case Sell = 2;
    case Move = 3;
    case Transfer = 6;
    case CashOut = 7;
    case Use = 8;
    case CashIn = 9;
    case Adjust = 12;
    case Return = 15;
    case Production = 16;
    case ReturnSupplier = 17;
    case Depreciation = 18;

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Buy',
            self::Sell => 'Sell',
            self::Move => 'Move',
            self::Transfer => 'Transfer',
            self::CashOut => 'Cash Out',
            self::Use => 'Use Items',
            self::CashIn => 'Cash In',
            self::Adjust => 'Adjust',
            self::Return => 'Return',
            self::Production => 'Production',
            self::ReturnSupplier => 'Ret. Supplier',
            self::Depreciation => 'Depreciation',
        };
    }

    public function priceSource(): string
    {
        return match ($this) {
            self::Buy, self::ReturnSupplier, self::Production => 'cost',
            default => 'price',
        };
    }

    public function isNegative(): bool
    {
        return in_array($this, [self::Sell, self::ReturnSupplier, self::CashOut], true);
    }

    public function hasItems(): bool
    {
        return in_array($this, [
            self::Buy, self::Sell, self::Move,
            self::Return, self::ReturnSupplier,
            self::Production, self::Use,
        ], true);
    }
}
