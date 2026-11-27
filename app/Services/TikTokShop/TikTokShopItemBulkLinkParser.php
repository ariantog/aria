<?php

namespace App\Services\TikTokShop;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class TikTokShopItemBulkLinkParser
{
    public const MAX_ROWS = 15000;

    /**
     * @return list<array{
     *     line: int,
     *     code: ?string,
     *     aria_item_id: ?int,
     *     tiktok_product_id: ?string,
     *     tiktok_sku_id: ?string,
     * }>
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File tidak dapat dibaca.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($path);
        }

        return $this->parseSpreadsheet($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            throw new RuntimeException('Sheet kosong.');
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow)) {
            throw new RuntimeException('Baris header tidak ditemukan.');
        }

        $columns = $this->mapHeaders($headerRow);

        return $this->buildRows($matrix, $columns, startLine: 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibuka.');
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);
            throw new RuntimeException('Baris header CSV tidak ditemukan.');
        }

        $columns = $this->mapHeaders($headerRow);
        $matrix = [];
        while (($row = fgetcsv($handle)) !== false) {
            $matrix[] = $row;
        }
        fclose($handle);

        return $this->buildRows($matrix, $columns, startLine: 2);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{code: ?int, aria_item_id: ?int, tiktok_product_id: ?int, tiktok_sku_id: ?int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $tiktokExport = $this->detectTikTokProductExportColumns($headerRow);
        if ($tiktokExport !== null) {
            return $tiktokExport;
        }

        $columns = [
            'code' => null,
            'aria_item_id' => null,
            'tiktok_product_id' => null,
            'tiktok_sku_id' => null,
        ];

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if ($key === '') {
                continue;
            }

            if (in_array($key, ['code', 'sku', 'itemcode', 'item_code', 'ariacode', 'aria_code', 'kode', 'kodesku', 'seller_sku', 'sellersku'], true)) {
                $columns['code'] = (int) $index;

                continue;
            }

            if (in_array($key, ['aria_item_id', 'aria_id', 'ariaid', 'id_item', 'item_id_aria'], true)) {
                $columns['aria_item_id'] = (int) $index;

                continue;
            }

            if (in_array($key, [
                'tiktok_shop_product_id', 'tiktok_product_id', 'product_id', 'productid',
                'tiktok_product', 'id_produk', 'kode_produk',
            ], true)) {
                $columns['tiktok_product_id'] = (int) $index;

                continue;
            }

            if (in_array($key, [
                'tiktok_shop_sku_id', 'tiktok_sku_id', 'sku_id', 'skuid',
                'tiktok_sku', 'id_sku', 'kode_sku',
            ], true)) {
                $columns['tiktok_sku_id'] = (int) $index;

                continue;
            }
        }

        if ($columns['tiktok_sku_id'] === null && $columns['code'] === null && $columns['aria_item_id'] === null) {
            throw new RuntimeException('Kolom wajib: seller_sku / SKU Aria atau code/sku.');
        }

        if ($columns['tiktok_product_id'] === null && $columns['tiktok_sku_id'] === null) {
            throw new RuntimeException('Kolom wajib: product_id dan/atau sku_id TikTok Shop.');
        }

        if ($columns['code'] === null && $columns['aria_item_id'] === null) {
            throw new RuntimeException('Kolom wajib: seller_sku / SKU Aria atau aria_item_id.');
        }

        return $columns;
    }

    /**
     * TikTok seller product export: Product ID | SKU ID | Seller SKU (nama kolom bervariasi).
     *
     * @param  array<int, mixed>  $headerRow
     * @return array{code: ?int, aria_item_id: ?int, tiktok_product_id: ?int, tiktok_sku_id: ?int}|null
     */
    private function detectTikTokProductExportColumns(array $headerRow): ?array
    {
        $indices = [
            'product_id' => null,
            'sku_id' => null,
            'seller_sku' => null,
        ];

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if (in_array($key, ['product_id', 'tiktok_product_id', 'tiktok_shop_product_id'], true)) {
                $indices['product_id'] = (int) $index;
            } elseif (in_array($key, ['sku_id', 'tiktok_sku_id', 'tiktok_shop_sku_id'], true)) {
                $indices['sku_id'] = (int) $index;
            } elseif (in_array($key, ['seller_sku', 'seller sku', 'sellersku'], true)) {
                $indices['seller_sku'] = (int) $index;
            }
        }

        if ($indices['sku_id'] === null || $indices['seller_sku'] === null) {
            return null;
        }

        return [
            'code' => $indices['seller_sku'],
            'aria_item_id' => null,
            'tiktok_product_id' => $indices['product_id'],
            'tiktok_sku_id' => $indices['sku_id'],
        ];
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     * @param  array{code: ?int, aria_item_id: ?int, tiktok_product_id: ?int, tiktok_sku_id: ?int}  $columns
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $matrix, array $columns, int $startLine): array
    {
        $rows = [];
        $line = $startLine;

        foreach ($matrix as $rawRow) {
            if (! is_array($rawRow)) {
                $line++;

                continue;
            }

            $code = $columns['code'] !== null
                ? trim((string) ($rawRow[$columns['code']] ?? ''))
                : '';
            $ariaItemId = $columns['aria_item_id'] !== null
                ? $this->parseIntCell($rawRow[$columns['aria_item_id']] ?? null)
                : null;
            $productId = $columns['tiktok_product_id'] !== null
                ? $this->parseIdCell($rawRow[$columns['tiktok_product_id']] ?? null)
                : null;
            $skuId = $columns['tiktok_sku_id'] !== null
                ? $this->parseIdCell($rawRow[$columns['tiktok_sku_id']] ?? null)
                : null;

            if ($code === '' && ($ariaItemId === null || $ariaItemId <= 0)
                && ($productId === null || $productId === '')
                && ($skuId === null || $skuId === '')) {
                $line++;

                continue;
            }

            $rows[] = [
                'line' => $line,
                'code' => $code !== '' ? $code : null,
                'aria_item_id' => $ariaItemId,
                'tiktok_product_id' => $productId,
                'tiktok_sku_id' => $skuId,
            ];

            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException('Maksimum '.self::MAX_ROWS.' baris data per upload.');
            }

            $line++;
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada baris data setelah header.');
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '-', '.'], '_', $value);

        return preg_replace('/_+/', '_', $value) ?? $value;
    }

    private function parseIntCell(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        $clean = preg_replace('/[^\d]/', '', (string) $value);
        if ($clean === '') {
            return null;
        }

        $int = (int) $clean;

        return $int > 0 ? $int : null;
    }

    private function parseIdCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            $value = sprintf('%.0f', $value);
        }

        $id = trim((string) $value);
        if ($id === '') {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', $id);

        return $digits !== '' ? $digits : $id;
    }
}