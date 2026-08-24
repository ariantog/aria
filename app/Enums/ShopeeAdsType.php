<?php

namespace App\Enums;

enum ShopeeAdsType: string
{
    case GmvMax = 'gmv_max';
    case ProdukManual = 'iklan_produk_manual';

    // Legacy schedule keys — API not functional; kept for old schedule rows / history.
    case TokoAuto = 'iklan_toko_auto';
    case TokoManual = 'iklan_toko_manual';
    case ProdukAuto = 'iklan_produk_auto';
    case Group = 'iklan_group';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::GmvMax->value => 'Iklan Produk GMV Max',
            self::ProdukManual->value => 'Iklan Produk (Individual)',
            self::TokoAuto->value => 'Iklan Toko Auto (legacy — tidak aktif)',
            self::TokoManual->value => 'Iklan Toko Manual (legacy — tidak aktif)',
            self::ProdukAuto->value => 'Iklan Produk Otomatis (legacy — tidak aktif)',
            self::Group->value => 'Iklan Group (legacy — tidak aktif)',
        ];
    }

    /**
     * Ad types the engine actually runs against Shopee APIs.
     *
     * @return list<string>
     */
    public static function supportedScheduleTypes(): array
    {
        return [
            self::GmvMax->value,
            self::ProdukManual->value,
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    public function isSupported(): bool
    {
        return in_array($this->value, self::supportedScheduleTypes(), true);
    }

    public static function normalizeScheduleType(string $value): ?self
    {
        $key = strtolower(trim($value));

        return match ($key) {
            'gmv', 'gmv_max', 'gmvmax', 'gmv_max_roas', 'produk_gmv' => self::GmvMax,
            'item', 'item_ads', 'itemads', 'produk_manual', 'individual', 'iklan_produk_manual' => self::ProdukManual,
            'toko_auto', 'booster', 'iklan_toko_auto' => self::TokoAuto,
            'toko_manual', 'iklan_toko_manual' => self::TokoManual,
            'produk_auto', 'produk_otomatis', 'iklan_produk_auto' => self::ProdukAuto,
            'group', 'iklan_group' => self::Group,
            default => null,
        };
    }
}
