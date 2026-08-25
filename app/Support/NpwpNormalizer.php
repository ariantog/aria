<?php

namespace App\Support;

class NpwpNormalizer
{
    public static function digits(?string $npwp): string
    {
        return preg_replace('/\D+/', '', (string) $npwp) ?? '';
    }

    public static function matches(?string $left, ?string $right): bool
    {
        $a = self::digits($left);
        $b = self::digits($right);

        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b;
    }
}
