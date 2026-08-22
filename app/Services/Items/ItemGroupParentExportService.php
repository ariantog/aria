<?php

namespace App\Services\Items;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemGroupParentExportService
{
    public function __construct(
        protected ItemGroupHierarchyService $groupHierarchy,
    ) {}

    public function download(string $parentKey): StreamedResponse
    {
        $payload = $this->groupHierarchy->exportPayload($parentKey);

        abort_if($payload === null, 404);

        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Group Stock');

        $fixedHeaders = [
            'ID',
            'Item Name',
            'Item Code',
            'Color Code',
            'Color Name',
            'Color Pcode',
            'Size',
        ];

        $warehouseHeaders = $payload['warehouse_names'];
        $jubelioHeaders = ['Jubelio On Hand', 'Jubelio Available'];
        $headers = array_merge($fixedHeaders, $warehouseHeaders, ['Aria Total'], $jubelioHeaders);

        foreach ($headers as $colIndex => $header) {
            $col = $colIndex + 1;
            $worksheet->setCellValueByColumnAndRow($col, 1, $header);
            $worksheet->getStyleByColumnAndRow($col, 1)->getFont()->setBold(true);
        }

        $rowNum = 2;
        foreach ($payload['rows'] as $row) {
            $col = 1;
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_id']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_name']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['item_code']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['color_code']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['color_name']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['color_pcode']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['size']);

            foreach ($warehouseHeaders as $warehouseName) {
                $worksheet->setCellValueByColumnAndRow(
                    $col++,
                    $rowNum,
                    $row['warehouse_qtys'][$warehouseName] ?? 0
                );
            }

            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['aria_total']);
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['jubelio_on_hand'] ?? '');
            $worksheet->setCellValueByColumnAndRow($col++, $rowNum, $row['jubelio_available'] ?? '');
            $rowNum++;
        }

        if ($payload['rows'] === []) {
            $worksheet->setCellValue('A2', 'No SKUs in this group.');
        }

        $filename = sprintf(
            'item-group-%s-%s.xlsx',
            \Illuminate\Support\Str::slug($payload['label']),
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
