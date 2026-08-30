<?php

namespace App\Services\InventoryHealth;

class InventoryHealthClassifier
{
    public const LOW_COVER_DAYS = 14;

    public const OVERSTOCK_COVER_DAYS = 90;

    public const DEAD = 'dead';

    public const SLOW = 'slow';

    public const LOW = 'low';

    public const OVERSTOCK = 'overstock';

    public const HEALTHY = 'healthy';

    public const INACTIVE = 'inactive';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            '' => 'All statuses',
            self::LOW => 'Fast Moving / Low Stock',
            self::HEALTHY => 'Healthy',
            self::OVERSTOCK => 'Overstock',
            self::SLOW => 'Slow Moving',
            self::DEAD => 'Dead Stock',
            self::INACTIVE => 'Inactive',
        ];
    }

    /**
     * Classify one SKU from net sell qty (sell minus return) and current stock.
     *
     * Days of cover = stock / (net_period / period_days). The old report treated
     * any 30-day sale as "Healthy" and ignored returns — that is rejected here.
     *
     * @return array{key: string, label: string, color: string, rec: string, days_of_cover: ?float}
     */
    public static function classify(float $stock, float $netPeriod, float $netExtended, int $periodDays): array
    {
        $stock = max(0.0, $stock);
        $netPeriod = max(0.0, $netPeriod);
        $netExtended = max(0.0, $netExtended);
        $periodDays = max(1, $periodDays);
        $cover = $netPeriod > 0.0 ? $stock / ($netPeriod / $periodDays) : null;

        if ($stock > 0.0 && $netExtended <= 0.0) {
            return self::result(self::DEAD, $cover);
        }

        if ($stock > 0.0 && $netPeriod <= 0.0 && $netExtended > 0.0) {
            return self::result(self::SLOW, $cover);
        }

        if ($netPeriod > 0.0 && ($stock <= 0.0 || $cover < self::LOW_COVER_DAYS)) {
            return self::result(self::LOW, $cover);
        }

        if ($netPeriod > 0.0 && $stock > 0.0 && $cover > self::OVERSTOCK_COVER_DAYS) {
            return self::result(self::OVERSTOCK, $cover);
        }

        if ($netPeriod > 0.0 && $stock > 0.0) {
            return self::result(self::HEALTHY, $cover);
        }

        return self::result(self::INACTIVE, $cover);
    }

    /**
     * @return array{key: string, label: string, color: string, rec: string, days_of_cover: ?float}
     */
    private static function result(string $key, ?float $cover): array
    {
        return match ($key) {
            self::DEAD => [
                'key' => self::DEAD,
                'label' => 'Dead Stock',
                'color' => 'bg-rose-500',
                'rec' => 'No net sales in 90 days. Move or clear the stock.',
                'days_of_cover' => $cover,
            ],
            self::SLOW => [
                'key' => self::SLOW,
                'label' => 'Slow Moving',
                'color' => 'bg-gray-500',
                'rec' => 'Sold in 90 days but not in the selected period. Reduce stock.',
                'days_of_cover' => $cover,
            ],
            self::LOW => [
                'key' => self::LOW,
                'label' => 'Fast Moving / Low Stock',
                'color' => 'bg-amber-500',
                'rec' => 'Cover is under '.self::LOW_COVER_DAYS.' days. Restock.',
                'days_of_cover' => $cover,
            ],
            self::OVERSTOCK => [
                'key' => self::OVERSTOCK,
                'label' => 'Overstock',
                'color' => 'bg-sky-500',
                'rec' => 'Cover is over '.self::OVERSTOCK_COVER_DAYS.' days. Stop replenishing.',
                'days_of_cover' => $cover,
            ],
            self::HEALTHY => [
                'key' => self::HEALTHY,
                'label' => 'Healthy',
                'color' => 'bg-emerald-500',
                'rec' => 'Cover is between '.self::LOW_COVER_DAYS.' and '.self::OVERSTOCK_COVER_DAYS.' days.',
                'days_of_cover' => $cover,
            ],
            default => [
                'key' => self::INACTIVE,
                'label' => 'Inactive',
                'color' => 'bg-gray-300',
                'rec' => 'No stock and no recent net sales.',
                'days_of_cover' => $cover,
            ],
        };
    }
}
