<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseArrangementExportService
{
    public function download(array $suggestions, string $warehouseName, int $demandDays): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Arrangement');

        $slotCount = min(
            WarehouseArrangementService::MAX_SOURCE_SLOTS,
            (int) collect($suggestions)->max(fn (array $s) => count($s['sources'] ?? []))
        );

        $headers = [
            'Master Pcode',
            'Product Name',
            'Family Demand',
            'Completeness %',
            'SKU Code',
            'SKU Name',
            'Color',
            'Size',
            'SKU Demand',
        ];

        for ($i = 1; $i <= $slotCount; $i++) {
            $headers[] = "Warehouse {$i}";
            $headers[] = "Stock {$i}";
        }

        $headers[] = 'To Warehouse';
        $headers[] = 'Suggested Qty (chosen)';

        foreach ($headers as $colIndex => $header) {
            $worksheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
            $worksheet->getStyleByColumnAndRow($colIndex + 1, 1)->getFont()->setBold(true);
        }

        $rowNum = 2;
        foreach ($suggestions as $row) {
            $chosen = $row['sources'][0] ?? null;
            $col = 1;
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['master']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['master_name']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['family_demand_score']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['completeness_pct']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_code']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_name']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['warna']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['size']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_demand']);

            for ($slot = 0; $slot < $slotCount; $slot++) {
                $source = $row['sources'][$slot] ?? null;
                $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $source['from_warehouse_name'] ?? '');
                $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $source['source_stock'] ?? '');
            }

            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['to_warehouse_name']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $chosen['suggested_qty'] ?? '');
            $rowNum++;
        }

        if ($suggestions === []) {
            $worksheet->setCellValue('A2', 'No arrangement suggestions for this warehouse and period.');
        }

        $filename = sprintf(
            'warehouse-arrangement-%s-%dd-%s.xlsx',
            \Illuminate\Support\Str::slug($warehouseName),
            $demandDays,
            now()->format('Y-m-d'),
        );

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
