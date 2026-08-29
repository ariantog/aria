<?php

namespace App\Support;

use Illuminate\Http\Request;

class TransactionItemViewOptions
{
    /**
     * @return array{showImage: bool, showBarcode: bool, showSku: bool, showName: bool, showDescription: bool}
     */
    public static function defaults(): array
    {
        return [
            'showImage' => true,
            'showBarcode' => true,
            'showSku' => false,
            'showName' => true,
            'showDescription' => false,
        ];
    }

    /**
     * @return array{showImage: bool, showBarcode: bool, showSku: bool, showName: bool, showDescription: bool}
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();
        $defaults = self::defaults();

        return [
            'showImage' => $request->has('image') ? $request->boolean('image') : $defaults['showImage'],
            'showBarcode' => $request->has('barcode') ? $request->boolean('barcode') : $defaults['showBarcode'],
            'showSku' => $request->has('sku') ? $request->boolean('sku') : $defaults['showSku'],
            'showName' => $request->has('name') ? $request->boolean('name') : $defaults['showName'],
            'showDescription' => $request->has('desc') ? $request->boolean('desc') : $defaults['showDescription'],
        ];
    }

    /**
     * @param  array{showImage: bool, showBarcode: bool, showSku: bool, showName: bool, showDescription: bool}  $itemView
     */
    public static function leadingColumnCount(array $itemView): int
    {
        return collect($itemView)
            ->only(['showImage', 'showBarcode', 'showSku', 'showName', 'showDescription'])
            ->filter()
            ->count();
    }
}
