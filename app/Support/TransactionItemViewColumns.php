<?php

namespace App\Support;

use Illuminate\Http\Request;

class TransactionItemViewColumns
{
    /**
     * @return array{image: bool, barcode: bool, sku: bool, name: bool, description: bool}
     */
    public static function defaults(): array
    {
        return [
            'image' => true,
            'barcode' => true,
            'sku' => false,
            'name' => true,
            'description' => false,
        ];
    }

    /**
     * @return array{image: bool, barcode: bool, sku: bool, name: bool, description: bool}
     */
    public static function fromRequest(Request $request): array
    {
        $defaults = self::defaults();

        return [
            'image' => $request->has('image') ? $request->boolean('image') : $defaults['image'],
            'barcode' => $request->has('barcode') ? $request->boolean('barcode') : $defaults['barcode'],
            'sku' => $request->has('sku') ? $request->boolean('sku') : $defaults['sku'],
            'name' => $request->has('name') ? $request->boolean('name') : $defaults['name'],
            'description' => $request->has('desc') ? $request->boolean('desc') : $defaults['description'],
        ];
    }

    /**
     * @param  array{image: bool, barcode: bool, sku: bool, name: bool, description: bool}  $columns
     */
    public static function toQueryString(array $columns): string
    {
        return http_build_query([
            'image' => $columns['image'] ? '1' : '0',
            'barcode' => $columns['barcode'] ? '1' : '0',
            'sku' => $columns['sku'] ? '1' : '0',
            'name' => $columns['name'] ? '1' : '0',
            'desc' => $columns['description'] ? '1' : '0',
        ]);
    }

    /**
     * @param  array{image: bool, barcode: bool, sku: bool, name: bool, description: bool}  $columns
     */
    public static function visibleItemColumnCount(array $columns): int
    {
        return collect($columns)->filter()->count();
    }
}
