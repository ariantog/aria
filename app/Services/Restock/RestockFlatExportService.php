<?php

namespace App\Services\Restock;

use App\Models\RestockSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockFlatExportService
{
    /**
     * One worksheet: every restock sheet, rows where source stage qty &gt; 0.
     */
    public function downloadAll(string $source): StreamedResponse
    {
        $meta = RestockFlatPipeline::fromExportSource($source);
        $sourceField = $meta['source_field'];

        $rows = $this->collectRows($sourceField);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Flat');
        $sheet->fromArray(['sku', 'quantity'], null, 'A1');

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue('A'.$rowNum, $row['sku']);
            $sheet->setCellValue('B'.$rowNum, $row['quantity']);
            $rowNum++;
        }

        $filename = sprintf('restock-flat-%s-%s.xlsx', $source, now()->format('Y-m-d'));

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return list<array{sku: string, quantity: int}>
     */
    public function collectRows(string $sourceField): array
    {
        if (! in_array($sourceField, ['qty_restock', 'qty_production'], true)) {
            return [];
        }

        $cells = RestockSheet::query()
            ->with(['cells.item:id,code,legacy_code'])
            ->orderBy('name')
            ->get()
            ->flatMap(fn (RestockSheet $sheet) => $sheet->cells);

        $rows = [];
        foreach ($cells as $cell) {
            $qty = (int) $cell->{$sourceField};
            if ($qty <= 0) {
                continue;
            }

            $code = $cell->item?->code;
            if ($code === null || trim($code) === '') {
                continue;
            }

            $rows[] = [
                'sku' => $code,
                'quantity' => $qty,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['sku'], $b['sku']));

        return $rows;
    }
}
