<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

class TransactionListExportService
{
    private const TYPE_LABELS = [
        1 => 'Buy', 2 => 'Sell', 3 => 'Move', 6 => 'Transfer', 7 => 'Cash Out',
        8 => 'Use', 9 => 'Cash In', 12 => 'Adjust', 15 => 'Return',
        16 => 'Production', 17 => 'Ret. Supplier', 18 => 'Depreciation',
    ];

    /**
     * @param  Traversable<int, \App\Models\Transaction>|LazyCollection<int, \App\Models\Transaction>  $rows
     */
    public function downloadCsv(Traversable|LazyCollection $rows, string $filename, bool $hideBankBalances = false): StreamedResponse
    {
        return new StreamedResponse(function () use ($rows, $hideBankBalances) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Invoice', 'Type', 'Description', 'Sender', 'Sender Balance', 'Receiver', 'Receiver Balance', 'Items', 'Total']);
            foreach ($rows as $tx) {
                fputcsv($out, $this->csvRow($tx, $hideBankBalances));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

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
            $sheet->fromArray($this->excelRow($tx, $hideBankBalances), null, 'A'.$rowNum);
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

    /**
     * @return list<int|float|string|null>
     */
    private function excelRow(\App\Models\Transaction $tx, bool $hideBankBalances): array
    {
        $senderIsBank = $tx->sender?->type_slug === 'bank';
        $receiverIsBank = $tx->receiver?->type_slug === 'bank';

        return [
            $tx->date ? \Illuminate\Support\Carbon::parse($tx->date)->format('d/m/Y') : '',
            $tx->invoice,
            self::TYPE_LABELS[(int) $tx->type] ?? 'Unknown',
            $tx->sender?->name ?? '',
            ($hideBankBalances && $senderIsBank) ? '' : (float) $tx->sender_balance,
            $tx->receiver?->name ?? '',
            ($hideBankBalances && $receiverIsBank) ? '' : (float) $tx->receiver_balance,
            (float) $tx->total_items,
            (float) $tx->total,
        ];
    }

    /**
     * @return list<int|float|string|null>
     */
    private function csvRow(\App\Models\Transaction $tx, bool $hideBankBalances): array
    {
        $note = $tx->description ?: ($tx->notes ?: '');
        $senderIsBank = $tx->sender?->type_slug === 'bank';
        $receiverIsBank = $tx->receiver?->type_slug === 'bank';

        return [
            $tx->date ? \Illuminate\Support\Carbon::parse($tx->date)->format('d/m/Y') : '',
            $tx->invoice,
            self::TYPE_LABELS[(int) $tx->type] ?? 'Unknown',
            $note,
            $tx->sender?->name ?? '',
            ($hideBankBalances && $senderIsBank) ? '' : (float) $tx->sender_balance,
            $tx->receiver?->name ?? '',
            ($hideBankBalances && $receiverIsBank) ? '' : (float) $tx->receiver_balance,
            (float) $tx->total_items,
            (float) $tx->displaySignedGrandTotal(),
        ];
    }
}
