<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use Illuminate\Database\Eloquent\Builder;

class JubelioOrderReadyToShip
{
    public const STATUS = 'READY TO SHIP';

    public static function isReadyToShipStatus(mixed $status): bool
    {
        return strtoupper(trim((string) $status)) === self::STATUS;
    }

    public static function isHeldForCourier(Jubelioorder $order): bool
    {
        return $order->type === 'SELL' && self::isReadyToShipStatus($order->order_status);
    }

    /**
     * @param  Builder<Jubelioorder>  $query
     * @return Builder<Jubelioorder>
     */
    public static function scopeExcludingCourierHold(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereRaw('UPPER(TRIM(order_status)) != ?', [self::STATUS])
                ->orWhereNull('order_status');
        });
    }

    public static function sourceLabel(int $source): string
    {
        return match ($source) {
            Jubelioorder::SOURCE_WEBHOOK => 'Webhook',
            Jubelioorder::SOURCE_GET_ORDERS => 'Get orders',
            Jubelioorder::SOURCE_SCAN_RESI => 'Scan resi',
            Jubelioorder::SOURCE_SHOPEE_PUSH => 'Shopee push',
            default => 'Unknown ('.$source.')',
        };
    }
}
