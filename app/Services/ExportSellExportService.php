<?php

namespace App\Services;

use App\Models\TransactionDetail;
use App\Support\ExportSellRowGrouping;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSellExportService
{
    /**
     * @param  Collection<int, TransactionDetail>  $rows
     * @param  list<string>  $visibleTransactionColumns
     */
    public function download(Collection $rows, array $visibleTransactionColumns = []): StreamedResponse
    {
        $queryService = app(ExportSellQueryService::class);
        $columnLabels = $queryService->optionalTransactionColumnLabels();
        $grouping = new ExportSellRowGrouping;

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
        ];

        foreach ($visibleTransactionColumns as $columnKey) {
            $headers[] = $columnLabels[$columnKey] ?? $columnKey;
        }

        $headers[] = 'Sender';
        $headers[] = 'Receiver';

        $sheet->fromArray($headers, null, 'A1');
        $lastColumn = $this->columnLetter(count($headers));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $detail) {
            $transactionId = (int) $detail->transaction_id;
            $isFirstLine = $grouping->isFirstLineForTransaction($transactionId);
            $grouping->advance($transactionId);

            $typeLabel = TransactionDetail::typeLabel((int) $detail->transaction_type);
            $transaction = $detail->transaction;
            $sender = $detail->sender ?? $transaction?->sender;
            $receiver = $detail->receiver ?? $transaction?->receiver;

            $row = [
                $grouping->formattedDate($detail, $isFirstLine),
                $typeLabel,
                $transaction?->invoice ?? '',
                (int) $detail->item_id,
                $detail->item?->code ?? '',
                (float) $detail->quantity,
                (float) $detail->discount,
                (float) $detail->total,
            ];

            foreach ($visibleTransactionColumns as $columnKey) {
                $row[] = $grouping->transactionColumnValue($columnKey, $transaction, $isFirstLine);
            }

            $row[] = $sender?->name ?? '';
            $row[] = $receiver?->name ?? '';

            $sheet->fromArray($row, null, 'A'.$rowNum);

            $rowNum++;
        }

        foreach (range('A', $lastColumn) as $col) {
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

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)).$letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
