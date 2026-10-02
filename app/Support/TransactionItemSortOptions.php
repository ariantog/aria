<?php

namespace App\Support;

use Illuminate\Http\Request;

class TransactionItemSortOptions
{
    public const DEFAULT_COLUMN = 'sku';

    public const DEFAULT_DIRECTION = 'asc';

    /** @var list<string> */
    public const COLUMNS = [
        'barcode',
        'sku',
        'name',
        'desc',
        'qty',
        'price',
        'disc',
        'subtotal',
    ];

    /**
     * @return array{col: string|null, dir: string}
     */
    public static function defaults(): array
    {
        return [
            'col' => self::DEFAULT_COLUMN,
            'dir' => self::DEFAULT_DIRECTION,
        ];
    }

    /**
     * @return array{col: string|null, dir: string}
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();
        $defaults = self::defaults();

        $col = $request->query('sort_col', $request->input('sort_col'));
        if ($col === null || $col === '') {
            $col = $defaults['col'];
        }

        $col = is_string($col) ? strtolower(trim($col)) : $defaults['col'];
        if (! in_array($col, self::COLUMNS, true)) {
            $col = $defaults['col'];
        }

        $dir = $request->query('sort_dir', $request->input('sort_dir', $defaults['dir']));
        $dir = is_string($dir) ? strtolower(trim($dir)) : $defaults['dir'];
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaults['dir'];
        }

        return [
            'col' => $col,
            'dir' => $dir,
        ];
    }
}
