<?php

namespace App\Services;

use App\Models\TransactionDetail;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSellExportService
{
    /**
     * @param  Collection<int, TransactionDetail>  $rows
     */
    public function download(Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export Sell');

        $headers = [
            'Date',
            'Type',
            'Invoice',
            'Item ID',
            'Item Code',
            'Qty',
            'Discount %',
            'Subtotal',
            'Sender',
            'Receiver',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $detail) {
            $typeLabel = TransactionDetail::typeLabel((int) $detail->transaction_type);

            $sheet->fromArray([
                $detail->date ? \Illuminate\Support\Carbon::parse($detail->date)->format('d/m/Y') : '',
                $typeLabel,
                $detail->transaction?->invoice ?? '',
                (int) $detail->item_id,
                $detail->item?->code ?? '',
                (float) $detail->quantity,
                (float) $detail->discount,
                (float) $detail->total,
                $detail->sender?->name ?? '',
                $detail->receiver?->name ?? '',
            ], null, 'A'.$rowNum);

            $rowNum++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'export-sell-'.now()->format('Y-m-d-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

}
