<?php

namespace App\Services\Jubelio;

/**
 * Jubelio occasionally sends qty "0" while qty_picked / qty_in_base are correct.
 */
class JubelioOrderLineQuantity
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function resolve(array $item, string $primaryKey = 'qty'): float
    {
        $keys = array_values(array_unique(array_filter([
            $primaryKey,
            'qty_picked',
            'qty_in_base',
            'qty',
        ])));

        foreach ($keys as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }

            $raw = $item[$key];
            if ($raw === null || $raw === '') {
                continue;
            }

            $qty = (float) $raw;
            if ($qty > 0) {
                return $qty;
            }
        }

        return 0.0;
    }
}
