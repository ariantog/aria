<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseStockExportService
{
    /**
     * @param  Collection<int, Item>  $items
     */
    public function download(Collection $items, string $warehouseName): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Warehouse Stock');

        $headers = [
            'Item ID',
            'Code',
            'Name',
            'Description',
            'Price',
            'Stock',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($items as $item) {
            $group = $item->group;
            $sheet->fromArray([
                (int) $item->id,
                $item->code ?? '',
                $group->description ?? $item->name ?? '',
                $item->description ?: ($group->description ?? ''),
                (float) $item->price,
                (float) ($item->pivot->quantity ?? 0),
            ], null, 'A'.$rowNum);

            $rowNum++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $warehouseName) ?: 'warehouse';
        $filename = 'warehouse-stock-'.$safeName.'-'.now()->format('Y-m-d-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
