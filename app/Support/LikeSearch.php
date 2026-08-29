<?php

namespace App\Support;

class LikeSearch
{
    /**
     * Turn user search text into a LIKE pattern segment.
     * Whitespace between tokens becomes a % wildcard.
     */
    public static function normalize(string $term): string
    {
        $term = trim($term);
        if ($term === '') {
            return '';
        }

        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return str_replace(' ', '%', self::escape($term));
    }

    public static function contains(string $term): string
    {
        $normalized = self::normalize($term);

        return $normalized === '' ? '%' : "%{$normalized}%";
    }

    public static function containsInsensitive(string $term): string
    {
        $normalized = strtolower(self::normalize($term));

        return $normalized === '' ? '%' : "%{$normalized}%";
    }

    public static function prefix(string $term): string
    {
        $normalized = self::normalize($term);

        return $normalized === '' ? '%' : "{$normalized}%";
    }

    private static function escape(string $term): string
    {
        return addcslashes($term, '%_\\');
    }
}
