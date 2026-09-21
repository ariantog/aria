<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Jubeliosync;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseStockExportService
{
    /**
     * @param  Collection<int, Item>  $items
     * @param  array<int, array{
     *     linked: bool,
     *     on_hand: ?float,
     *     on_order: ?float,
     *     reserved: ?float,
     *     available: ?float,
     *     mismatch: bool,
     * }>  $jubelioStocks
     */
    public function download(
        Collection $items,
        string $warehouseName,
        ?Jubeliosync $jubelioSync = null,
        array $jubelioStocks = [],
    ): StreamedResponse {
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

        if ($jubelioSync) {
            $headers[] = 'Jubelio On Hand';
            $headers[] = 'Jubelio On Order';
            $headers[] = 'Jubelio Reserved';
            $headers[] = 'Jubelio Available';
            $headers[] = 'Jubelio Linked';
        }

        $sheet->fromArray($headers, null, 'A1');
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($items as $item) {
            $row = [
                (int) $item->id,
                $item->code ?? '',
                $item->name ?? '',
                $item->catalogDescription(),
                (float) $item->price,
                (float) ($item->pivot->quantity ?? 0),
            ];

            if ($jubelioSync) {
                $jubelio = $jubelioStocks[$item->id] ?? null;
                if ($jubelio && $jubelio['linked']) {
                    $row[] = $jubelio['on_hand'];
                    $row[] = $jubelio['on_order'];
                    $row[] = $jubelio['reserved'];
                    $row[] = $jubelio['available'];
                    $row[] = 'Yes';
                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = 'No';
                }
            }

            $sheet->fromArray($row, null, 'A'.$rowNum);

            $rowNum++;
        }

        foreach (range('A', $lastCol) as $col) {
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
