<?php

namespace App\Enums;

enum ShopeeAdsType: string
{
    case TokoAuto = 'toko_auto';
    case TokoManual = 'toko_manual';
    case ProdukAuto = 'produk_auto';
    case Group = 'group';

  /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::TokoAuto->value => 'Iklan Toko Auto / Booster',
            self::TokoManual->value => 'Iklan Toko Manual',
            self::ProdukAuto->value => 'Iklan Produk Otomatis',
            self::Group->value => 'Iklan Group',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    /**
     * @return list<string>
     */
    public static function singleAdTypes(): array
    {
        return [
            self::TokoAuto->value,
            self::TokoManual->value,
            self::ProdukAuto->value,
        ];
    }

    public static function normalize(string $value): ?self
    {
        $normalized = match (strtolower($value)) {
            'toko_auto', 'booster' => self::TokoAuto,
            'toko_manual' => self::TokoManual,
            'produk_auto' => self::ProdukAuto,
            'group' => self::Group,
            default => null,
        };

        return $normalized;
    }
}
