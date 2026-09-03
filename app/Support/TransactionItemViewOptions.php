<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Http\Request;

class TransactionItemViewOptions
{
    /**
     * @return array{showImage: bool, showBarcode: bool, showSku: bool, showLegacyCode: bool, showName: bool, showDescription: bool}
     */
    public static function defaults(): array
    {
        return [
            'showImage' => true,
            'showBarcode' => true,
            'showSku' => false,
            'showLegacyCode' => false,
            'showName' => true,
            'showDescription' => false,
        ];
    }

    /**
     * @return array{showImage: bool, showBarcode: bool, showSku: bool, showLegacyCode: bool, showName: bool, showDescription: bool}
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();
        $defaults = self::defaults();

        $showLegacyCode = $request->has('legacy') ? $request->boolean('legacy') : $defaults['showLegacyCode'];
        $showSku = $request->has('sku') ? $request->boolean('sku') : $defaults['showSku'];

        if ($showLegacyCode && ! $showSku) {
            $showSku = true;
        }

        return [
            'showImage' => $request->has('image') ? $request->boolean('image') : $defaults['showImage'],
            'showBarcode' => $request->has('barcode') ? $request->boolean('barcode') : $defaults['showBarcode'],
            'showSku' => $showSku,
            'showLegacyCode' => $showLegacyCode,
            'showName' => $request->has('name') ? $request->boolean('name') : $defaults['showName'],
            'showDescription' => $request->has('desc') ? $request->boolean('desc') : $defaults['showDescription'],
        ];
    }

    /**
     * @param  array{showImage: bool, showBarcode: bool, showSku: bool, showLegacyCode?: bool, showName: bool, showDescription: bool}  $itemView
     */
    public static function showSkuColumn(array $itemView): bool
    {
        return ($itemView['showSku'] ?? false) || ($itemView['showLegacyCode'] ?? false);
    }

    /**
     * @param  array{showImage: bool, showBarcode: bool, showSku: bool, showLegacyCode?: bool, showName: bool, showDescription: bool}  $itemView
     */
    public static function skuColumnLabel(array $itemView): string
    {
        if (! self::showSkuColumn($itemView)) {
            return '';
        }

        return ($itemView['showLegacyCode'] ?? false) ? 'Legacy code' : 'SKU';
    }

    public static function skuColumnValue(?Item $item, array $itemView): string
    {
        if (! self::showSkuColumn($itemView)) {
            return '';
        }

        $code = trim((string) ($item?->code ?? ''));

        if ($itemView['showLegacyCode'] ?? false) {
            $legacy = $item?->distinctLegacyCode();

            return $legacy ?? ($code !== '' ? $code : '-');
        }

        return $code !== '' ? $code : '-';
    }

    /**
     * @param  array{showImage: bool, showBarcode: bool, showSku: bool, showLegacyCode?: bool, showName: bool, showDescription: bool}  $itemView
     */
    public static function leadingColumnCount(array $itemView): int
    {
        $count = 0;

        if ($itemView['showImage'] ?? false) {
            $count++;
        }
        if ($itemView['showBarcode'] ?? false) {
            $count++;
        }
        if (self::showSkuColumn($itemView)) {
            $count++;
        }
        if ($itemView['showName'] ?? false) {
            $count++;
        }
        if ($itemView['showDescription'] ?? false) {
            $count++;
        }

        return $count;
    }
}
