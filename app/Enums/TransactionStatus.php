<?php

namespace App\Enums;

enum TransactionStatus: int
{
    case Pending = 0;
    case Completed = 1;
    case Cancelled = 2;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
