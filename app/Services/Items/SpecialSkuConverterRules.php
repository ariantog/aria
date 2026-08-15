<?php

namespace App\Services\Items;

class SpecialSkuConverterRules
{
    public const FAILURE_NOT_SPECIAL = 'NOT_SPECIAL_SKU';

    public const FAILURE_INVALID_STRUCTURE = 'SPECIAL_SKU_INVALID';

    /**
     * @return array<int, array{
     *     id: string,
     *     label: string,
     *     pcode_prefix: string,
     *     sizes: array<int, string>,
     *     legacy_example: string,
     *     canonical_example: string
     * }>
     */
    public static function families(): array
    {
        return [
            [
                'id' => 'fabricband',
                'label' => 'Fabric Band',
                'pcode_prefix' => 'FABRICBAND-',
                'sizes' => ['LIGHT', 'MEDIUM', 'HEAVY'],
                'legacy_example' => 'FABRICBAND-03-LIGHT-BABYBLUE',
                'canonical_example' => 'FABRICBAND-03-BABYBLUE-LIGHT',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function pcodePrefixes(): array
    {
        return collect(self::families())
            ->pluck('pcode_prefix')
            ->unique()
            ->values()
            ->all();
    }

    public function matchesLegacyCode(string $code): bool
    {
        return $this->parseLegacyCode($code) !== null;
    }

    /**
     * @return array{
     *     family_id: string,
     *     pcode: string,
     *     size: string,
     *     color: string
     * }|null
     */
    public function parseLegacyCode(string $code): ?array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        foreach (self::families() as $family) {
            $sizes = implode('|', array_map(
                fn (string $size) => preg_quote($size, '/'),
                $family['sizes'],
            ));
            $prefix = preg_quote($family['pcode_prefix'], '/');
            $pattern = '/^('.$prefix.'\d+)-('.$sizes.')-([A-Z0-9]+)$/';

            if (preg_match($pattern, $code, $matches)) {
                return [
                    'family_id' => $family['id'],
                    'pcode' => $matches[1],
                    'size' => $matches[2],
                    'color' => $matches[3],
                ];
            }
        }

        return null;
    }

    public function buildCanonicalCode(string $pcode, string $color, string $size): string
    {
        return strtoupper(trim($pcode)).'-'.strtoupper(trim($color)).'-'.strtoupper(trim($size));
    }
}
