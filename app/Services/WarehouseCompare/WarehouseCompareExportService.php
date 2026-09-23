<?php

namespace App\Services\WarehouseCompare;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseCompareExportService
{
    /**
     * @param  array{warehouses: list<array{id: int, name: string}>, blocks: list<array<string, mixed>>}  $grid
     */
    public function download(array $grid, string $filenameBase): StreamedResponse
    {
        if (($grid['display_mode'] ?? '') === 'list') {
            return $this->downloadList($grid, $filenameBase);
        }

        return $this->downloadMatrix($grid, $filenameBase);
    }

    /**
     * @param  array{warehouses: list<array{id: int, name: string}>, blocks: list<array<string, mixed>>}  $grid
     */
    protected function downloadMatrix(array $grid, string $filenameBase): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock compare');

        $warehouses = $grid['warehouses'] ?? [];
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, 1, 'Product');
        $sheet->setCellValueByColumnAndRow($col++, 1, 'Color');

        $sizeHeaders = $this->collectSizeHeaders($grid['blocks'] ?? []);
        foreach ($sizeHeaders as $sizeLabel) {
            foreach ($warehouses as $warehouse) {
                $header = count($sizeHeaders) > 1 || $sizeLabel !== '—'
                    ? $warehouse['name'].' · '.$sizeLabel
                    : $warehouse['name'];
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $sheet->getStyleByColumnAndRow($col, 1)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD1FAE5');
                $col++;
            }
        }

        $rowNum = 2;
        foreach ($grid['blocks'] ?? [] as $block) {
            foreach ($block['rows'] ?? [] as $row) {
                if (($row['_type'] ?? '') === 'section') {
                    $sheet->setCellValue('A'.$rowNum, ($row['name'] ?? '').' ('.($row['pcode'] ?? '').')');
                    $rowNum++;

                    continue;
                }

                $col = 1;
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['pcode'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['color_name'] ?? '');

                foreach ($sizeHeaders as $sizeLabel) {
                    $prefix = $sizeLabel === '—' ? '' : str_replace(['.', ' '], '_', strtolower($sizeLabel)).'_';
                    $cells = $row['warehouses'][$prefix] ?? ($row['warehouses'][''] ?? null);
                    if ($cells === null && isset($row['warehouses']['total_'])) {
                        $cells = $row['warehouses']['total_'];
                        $prefix = 'total_';
                    }

                    foreach ($warehouses as $warehouse) {
                        $qty = is_array($cells) ? ($cells[(int) $warehouse['id']] ?? '') : '';
                        $sheet->setCellValueByColumnAndRow($col++, $rowNum, $qty);
                    }
                }

                $rowNum++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filenameBase).'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{warehouses: list<array{id: int, name: string}>, blocks: list<array<string, mixed>>}  $grid
     */
    protected function downloadList(array $grid, string $filenameBase): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock compare');

        $warehouses = $grid['warehouses'] ?? [];
        $headers = ['Item code', 'Name', 'Pcode', 'Color', 'Size', 'Sold 12 mo'];
        foreach ($warehouses as $warehouse) {
            $headers[] = $warehouse['name'];
        }

        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            if ($col > 6) {
                $sheet->getStyleByColumnAndRow($col, 1)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD1FAE5');
            }
            $col++;
        }

        $rowNum = 2;
        foreach ($grid['blocks'] ?? [] as $block) {
            foreach ($block['rows'] ?? [] as $row) {
                $col = 1;
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['code'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['name'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['pcode'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['color'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['size'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['sold_12m'] ?? 0);
                foreach ($warehouses as $warehouse) {
                    $wid = (int) $warehouse['id'];
                    $qty = $row['stocks'][$wid] ?? '';
                    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $qty);
                }
                $rowNum++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filenameBase).'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    protected function collectSizeHeaders(array $blocks): array
    {
        foreach ($blocks as $block) {
            if (($block['kind'] ?? '') === 'matrix' && ! empty($block['sizes'])) {
                return $block['sizes'];
            }
        }

        return ['—'];
    }
}
