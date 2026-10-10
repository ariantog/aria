<?php

namespace App\Services\Restock;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class RestockFlatImportParser
{
    public const MAX_ROWS = 50000;

    /**
     * @return list<array{line: int, sku: string, qty: int}>
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File cannot be read.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($path);
        }

        return $this->parseSpreadsheet($path);
    }

    /**
     * @return list<array{line: int, sku: string, qty: int}>
     */
    private function parseSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            throw new RuntimeException('Spreadsheet is empty.');
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow)) {
            throw new RuntimeException('Header row not found.');
        }

        $columns = $this->mapHeaders($headerRow);

        return $this->buildRows($matrix, $columns, startLine: 2);
    }

    /**
     * @return list<array{line: int, sku: string, qty: int}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not open CSV file.');
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);
            throw new RuntimeException('CSV header row not found.');
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
     * @return array{sku: int, qty: int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $skuCol = null;
        $qtyCol = null;

        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if ($key === '') {
                continue;
            }

            if (in_array($key, ['sku', 'code', 'item_code', 'itemcode', 'kode', 'kodesku'], true)) {
                $skuCol = (int) $index;

                continue;
            }

            if (in_array($key, ['quantity', 'qty', 'qty_move', 'amount', 'jumlah'], true)) {
                $qtyCol = (int) $index;
            }
        }

        if ($skuCol === null || $qtyCol === null) {
            throw new RuntimeException('Required columns: sku and quantity.');
        }

        return ['sku' => $skuCol, 'qty' => $qtyCol];
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     * @param  array{sku: int, qty: int}  $columns
     * @return list<array{line: int, sku: string, qty: int}>
     */
    private function buildRows(array $matrix, array $columns, int $startLine): array
    {
        $rows = [];
        $line = $startLine;

        foreach ($matrix as $row) {
            if ($line - $startLine >= self::MAX_ROWS) {
                break;
            }

            if (! is_array($row)) {
                $line++;

                continue;
            }

            $sku = strtoupper(trim((string) ($row[$columns['sku']] ?? '')));
            $qtyRaw = $row[$columns['qty']] ?? null;

            if ($sku === '' && ($qtyRaw === null || trim((string) $qtyRaw) === '')) {
                $line++;

                continue;
            }

            if ($sku === '') {
                throw new RuntimeException("Line {$line}: SKU is empty.");
            }

            $qty = (int) round((float) str_replace([',', ' '], '', (string) $qtyRaw));
            if ($qty < 0) {
                throw new RuntimeException("Line {$line}: quantity cannot be negative.");
            }

            $rows[] = [
                'line' => $line,
                'sku' => $sku,
                'qty' => $qty,
            ];

            $line++;
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s\-_]+/', '', $normalized) ?? '';

        return $normalized;
    }
}
