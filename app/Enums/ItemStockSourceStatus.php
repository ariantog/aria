<?php

namespace App\Enums;

enum ItemStockSourceStatus: string
{
    case Available = 'available';
    case SlowMoving = 'slow_moving';
    case DeadStock = 'dead_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::SlowMoving => 'Slow Moving',
            self::DeadStock => 'Dead Stock',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-100 text-emerald-800',
            self::SlowMoving => 'bg-amber-100 text-amber-800',
            self::DeadStock => 'bg-rose-100 text-rose-800',
        };
    }
}
