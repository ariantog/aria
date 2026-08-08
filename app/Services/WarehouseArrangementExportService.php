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
            'From Warehouse',
            'To Warehouse',
            'Source Stock',
            'Suggested Qty',
        ];

        foreach ($headers as $colIndex => $header) {
            $worksheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
            $worksheet->getStyleByColumnAndRow($colIndex + 1, 1)->getFont()->setBold(true);
        }

        $rowNum = 2;
        foreach ($suggestions as $row) {
            $worksheet->setCellValueByColumnAndRow(1, $rowNum, $row['master']);
            $worksheet->setCellValueByColumnAndRow(2, $rowNum, $row['master_name']);
            $worksheet->setCellValueByColumnAndRow(3, $rowNum, $row['family_demand_score']);
            $worksheet->setCellValueByColumnAndRow(4, $rowNum, $row['completeness_pct']);
            $worksheet->setCellValueByColumnAndRow(5, $rowNum, $row['item_code']);
            $worksheet->setCellValueByColumnAndRow(6, $rowNum, $row['item_name']);
            $worksheet->setCellValueByColumnAndRow(7, $rowNum, $row['warna']);
            $worksheet->setCellValueByColumnAndRow(8, $rowNum, $row['size']);
            $worksheet->setCellValueByColumnAndRow(9, $rowNum, $row['item_demand']);
            $worksheet->setCellValueByColumnAndRow(10, $rowNum, $row['from_warehouse_name']);
            $worksheet->setCellValueByColumnAndRow(11, $rowNum, $row['to_warehouse_name']);
            $worksheet->setCellValueByColumnAndRow(12, $rowNum, $row['source_stock']);
            $worksheet->setCellValueByColumnAndRow(13, $rowNum, $row['suggested_qty']);
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
