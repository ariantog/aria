<?php

namespace App\Services\Restock;

use InvalidArgumentException;

/**
 * Maps flat export source columns to pipeline move directions.
 */
final class RestockFlatPipeline
{
    public const SOURCE_RESTOCK = 'restock';

    public const SOURCE_PRODUCTION = 'production';

    public const DIRECTION_TO_PRODUCTION = 'to_production';

    public const DIRECTION_TO_SHIPPED = 'to_shipped';

    public const DIRECTION_SET_RESTOCK = 'set_restock';

    public static function isMoveDirection(string $direction): bool
    {
        return in_array($direction, [self::DIRECTION_TO_PRODUCTION, self::DIRECTION_TO_SHIPPED], true);
    }

    /**
     * @return array{source_field: string, move: string, label: string}
     */
    public static function fromDirection(string $direction): array
    {
        return match ($direction) {
            self::DIRECTION_TO_PRODUCTION => [
                'source_field' => 'qty_restock',
                'move' => self::DIRECTION_TO_PRODUCTION,
                'label' => 'Restock → Production',
            ],
            self::DIRECTION_TO_SHIPPED => [
                'source_field' => 'qty_production',
                'move' => self::DIRECTION_TO_SHIPPED,
                'label' => 'Production → Shipping',
            ],
            self::DIRECTION_SET_RESTOCK => [
                'source_field' => 'qty_restock',
                'move' => self::DIRECTION_SET_RESTOCK,
                'label' => 'Set restock qty',
            ],
            default => throw new InvalidArgumentException("Unknown direction: {$direction}"),
        };
    }

    /**
     * @return array{source_field: string, move: string, label: string}
     */
    public static function fromExportSource(string $source): array
    {
        return match ($source) {
            self::SOURCE_RESTOCK => self::fromDirection(self::DIRECTION_TO_PRODUCTION),
            self::SOURCE_PRODUCTION => self::fromDirection(self::DIRECTION_TO_SHIPPED),
            default => throw new InvalidArgumentException("Unknown export source: {$source}"),
        };
    }
}
