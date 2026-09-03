<?php

namespace App\Services\Reporting;

/**
 * Shared-token matcher for channel / warehouse / ledger names.
 *
 * "Biaya Shopee" ↔ "Shopee - CRYSTAL Customer", "Biaya Toko WTC" ↔ "Gudang WTC".
 */
class ChannelNameMatcher
{
    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'biaya', 'toko', 'gudang', 'customer', 'reseller', 'channel', 'account',
        'akun', 'shop', 'store', 'cost', 'the', 'and', 'dan', 'untuk', 'dari',
        'cv', 'pt', 'ud', 'of', 'to', 'at', 'in', 'on', 'by', 'for',
        'crystal', 'cipta', 'core', 'indosport', 'agm', 'uai', 'cakra', 'pribadi',
        'general', 'umum', 'lain', 'lainnya', 'misc',
    ];

    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'tiktok' => 'tiktok',
        'tik tok' => 'tiktok',
        'tik-tok' => 'tiktok',
        'toktok' => 'tiktok',
        'tokped' => 'tokopedia',
        'tokopedia' => 'tokopedia',
        'shopee' => 'shopee',
        'lazada' => 'lazada',
    ];

    /**
     * @return list<string>
     */
    public function tokens(string $name): array
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return [];
        }

        $normalized = strtr($normalized, $this->aliasReplacements());
        $normalized = (string) preg_replace('/[^a-z0-9]+/u', ' ', $normalized);
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, self::STOP_WORDS, true)) {
                continue;
            }
            $tokens[$part] = $part;
        }

        return array_values($tokens);
    }

    /**
     * @return list<string>
     */
    public function sharedTokens(string $left, string $right): array
    {
        return array_values(array_intersect($this->tokens($left), $this->tokens($right)));
    }

    public function score(string $left, string $right): int
    {
        $score = 0;
        foreach ($this->sharedTokens($left, $right) as $token) {
            $score += mb_strlen($token);
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $namesById
     * @return list<int>
     */
    public function matchingIds(string $needle, array $namesById): array
    {
        $matches = [];
        foreach ($namesById as $id => $name) {
            if ($this->score($needle, (string) $name) > 0) {
                $matches[] = (int) $id;
            }
        }

        return $matches;
    }

    /**
     * @return array<string, string>
     */
    private function aliasReplacements(): array
    {
        $replacements = [];
        foreach (self::ALIASES as $from => $to) {
            $replacements[$from] = $to;
        }

        return $replacements;
    }
}
