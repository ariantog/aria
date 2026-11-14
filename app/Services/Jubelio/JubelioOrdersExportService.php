<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JubelioOrdersExportService
{
    public function __construct(
        protected JubelioOrderIndexService $indexService,
    ) {}

    /**
     * @param  Collection<int, Jubelioorder>  $orders
     */
    public function download(Collection $orders): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jubelio Orders');

        $headers = [
            'Updated',
            'Txn Date',
            'Invoice',
            'Jubelio Order Status',
            'Store',
            'Customer',
            'Jubelio Warehouse',
            'Aria Warehouse',
            'Type',
            'Qty',
            'Total',
            'Sync Status',
            'Executed By',
            'Error',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($orders as $order) {
            $summary = $order->list_summary ?? $order->payloadSummary();
            $payloadDate = $summary['transaction_date'] ?? null;

            $row = [
                $this->formatDateTime($order->updated_at),
                $payloadDate ? $this->formatDateTime($payloadDate) : '',
                $order->invoice ?? '',
                $order->order_status ?? '',
                $summary['store_name'] ?? '',
                $summary['customer_name'] ?? '',
                $order->jubelio_warehouse ?: ($summary['location_name'] ?? ''),
                $order->aria_warehouse ?? '',
                $order->type ?? '',
                ($summary['item_count'] ?? 0) > 0 ? (int) $summary['item_count'] : '',
                isset($summary['real_total']) && $summary['real_total'] !== null
                    ? (float) $summary['real_total']
                    : '',
                $this->indexService->syncStatusLabel($order),
                $order->user?->name ?? '',
                JubelioOrderSyncStatus::shouldShowErrorLine($order) ? ($order->error ?? '') : '',
            ];

            $sheet->fromArray($row, null, 'A'.$rowNum);
            $rowNum++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'jubelio-orders-'.now()->format('Y-m-d-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }
}
