<?php

namespace App\Support;

class LikeSearch
{
    /**
     * Turn user search text into a LIKE pattern segment.
     * Whitespace between tokens becomes a % wildcard.
     */
    public static function normalize(string $term, bool $allowPercentWildcards = false): string
    {
        $term = trim($term);
        if ($term === '') {
            return '';
        }

        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return str_replace(' ', '%', self::escape($term, $allowPercentWildcards));
    }

    public static function contains(string $term, bool $allowPercentWildcards = false): string
    {
        $normalized = self::normalize($term, $allowPercentWildcards);

        return $normalized === '' ? '%' : "%{$normalized}%";
    }

    public static function containsInsensitive(string $term, bool $allowPercentWildcards = false): string
    {
        $normalized = strtolower(self::normalize($term, $allowPercentWildcards));

        return $normalized === '' ? '%' : "%{$normalized}%";
    }

    public static function prefix(string $term): string
    {
        $normalized = self::normalize($term);

        return $normalized === '' ? '%' : "{$normalized}%";
    }

    /**
     * True when the LIKE pattern would match every non-null value.
     */
    public static function isMatchAll(string $pattern): bool
    {
        return $pattern === '' || preg_match('/^%+$/', $pattern) === 1;
    }

    private static function escape(string $term, bool $allowPercentWildcards = false): string
    {
        return addcslashes($term, $allowPercentWildcards ? '_\\' : '%_\\');
    }
}
