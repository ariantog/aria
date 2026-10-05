<?php

namespace App\Services\Shopee;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ShopeeItemBulkLinkParser
{
    /** Shopee "DATA LENGKAP PRODUK" exports are ~11k+ rows. */
    public const MAX_ROWS = 15000;

    /**
     * @return list<array{
     *     line: int,
     *     code: ?string,
     *     aria_item_id: ?int,
     *     shopee_item_id: ?int,
     *     shopee_model_id: ?int,
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
     * @return array{code: ?int, aria_item_id: ?int, shopee_item_id: ?int, shopee_model_id: ?int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $shopeeExport = $this->detectShopeeProductExportColumns($headerRow);
        if ($shopeeExport !== null) {
            return $shopeeExport;
        }

        $columns = [
            'code' => null,
            'aria_item_id' => null,
            'shopee_item_id' => null,
            'shopee_model_id' => null,
        ];

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if ($key === '') {
                continue;
            }

            if (in_array($key, ['code', 'sku', 'itemcode', 'item_code', 'ariacode', 'aria_code', 'kode', 'kodesku', 'modelsku', 'model_sku'], true)) {
                $columns['code'] = (int) $index;

                continue;
            }

            if (in_array($key, ['aria_item_id', 'aria_id', 'ariaid', 'id_item', 'item_id_aria'], true)) {
                $columns['aria_item_id'] = (int) $index;

                continue;
            }

            if (in_array($key, ['shopee_item_id', 'shopeeitemid', 'shopee_id', 'shopeeitem', 'itemid_shopee', 'product_id', 'kode_produk'], true)) {
                $columns['shopee_item_id'] = (int) $index;

                continue;
            }

            if (in_array($key, ['shopee_model_id', 'shopeemodelid', 'model_id', 'modelid', 'variation_id', 'variasi_id', 'kode_variasi'], true)) {
                $columns['shopee_model_id'] = (int) $index;

                continue;
            }

            if ($key === 'item_id' && $columns['shopee_item_id'] === null) {
                $columns['shopee_item_id'] = (int) $index;
            }
        }

        if ($columns['shopee_model_id'] === null && $columns['code'] === null && $columns['aria_item_id'] === null) {
            throw new RuntimeException('Kolom wajib: SKU (kolom 3 export Shopee) atau code/sku.');
        }

        if ($columns['shopee_item_id'] === null && $columns['shopee_model_id'] === null) {
            throw new RuntimeException('Kolom wajib: Kode Variasi / shopee_model_id atau Kode Produk / shopee_item_id.');
        }

        if ($columns['code'] === null && $columns['aria_item_id'] === null) {
            throw new RuntimeException('Kolom wajib: SKU (kolom 3 export Shopee) atau code/sku.');
        }

        return $columns;
    }

    /**
     * Shopee seller export: Kode Produk | Kode Variasi | SKU (col 1 optional for matching; cols 2–3 drive link).
     *
     * @param  array<int, mixed>  $headerRow
     * @return array{code: ?int, aria_item_id: ?int, shopee_item_id: ?int, shopee_model_id: ?int}|null
     */
    private function detectShopeeProductExportColumns(array $headerRow): ?array
    {
        $indices = [
            'kode_produk' => null,
            'kode_variasi' => null,
            'sku' => null,
        ];

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if ($key === 'kode_produk') {
                $indices['kode_produk'] = (int) $index;
            } elseif ($key === 'kode_variasi') {
                $indices['kode_variasi'] = (int) $index;
            } elseif ($key === 'sku') {
                $indices['sku'] = (int) $index;
            }
        }

        if ($indices['kode_variasi'] === null || $indices['sku'] === null) {
            return null;
        }

        return [
            'code' => $indices['sku'],
            'aria_item_id' => null,
            'shopee_item_id' => $indices['kode_produk'],
            'shopee_model_id' => $indices['kode_variasi'],
        ];
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     * @param  array{code: ?int, aria_item_id: ?int, shopee_item_id: ?int, shopee_model_id: ?int}  $columns
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
            $shopeeItemId = $columns['shopee_item_id'] !== null
                ? $this->parseIntCell($rawRow[$columns['shopee_item_id']] ?? null)
                : null;
            $shopeeModelId = $columns['shopee_model_id'] !== null
                ? $this->parseIntCell($rawRow[$columns['shopee_model_id']] ?? null)
                : null;

            if ($code === '' && ($ariaItemId === null || $ariaItemId <= 0)
                && ($shopeeItemId === null || $shopeeItemId <= 0)
                && ($shopeeModelId === null || $shopeeModelId <= 0)) {
                $line++;

                continue;
            }

            $rows[] = [
                'line' => $line,
                'code' => $code !== '' ? $code : null,
                'aria_item_id' => $ariaItemId,
                'shopee_item_id' => $shopeeItemId,
                'shopee_model_id' => $shopeeModelId,
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
}
