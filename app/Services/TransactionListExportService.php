<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionListExportService
{
    /**
     * @param  LengthAwarePaginator<int, \App\Models\Transaction>  $rows
     */
    public function download(LengthAwarePaginator $rows, bool $hideBankBalances = false): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        $headers = ['Date', 'Invoice', 'Type', 'Sender', 'Sender Balance', 'Receiver', 'Receiver Balance', 'Items', 'Total'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $tx) {
            $typeValue = (int) $tx->type;
            $typeLabel = match ($typeValue) {
                1 => 'Buy', 2 => 'Sell', 3 => 'Move', 6 => 'Transfer', 7 => 'Cash Out',
                8 => 'Use', 9 => 'Cash In', 12 => 'Adjust', 15 => 'Return',
                16 => 'Production', 17 => 'Ret. Supplier', 18 => 'Depreciation',
                default => 'Unknown',
            };

            $senderIsBank = $tx->sender?->type_slug === 'bank';
            $receiverIsBank = $tx->receiver?->type_slug === 'bank';

            $sheet->fromArray([
                $tx->date ? \Illuminate\Support\Carbon::parse($tx->date)->format('d/m/Y') : '',
                $tx->invoice,
                $typeLabel,
                $tx->sender?->name ?? '',
                ($hideBankBalances && $senderIsBank) ? '' : (float) $tx->sender_balance,
                $tx->receiver?->name ?? '',
                ($hideBankBalances && $receiverIsBank) ? '' : (float) $tx->receiver_balance,
                (float) $tx->total_items,
                (float) $tx->total,
            ], null, 'A'.$rowNum);

            $rowNum++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'transactions-'.now()->format('Y-m-d-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
