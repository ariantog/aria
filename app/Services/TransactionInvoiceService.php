<?php

namespace App\Services;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class TransactionInvoiceService
{
    public function invoiceFileName(Transaction $transaction): string
    {
        return 'invoice_'.$transaction->id.'.pdf';
    }

    public function invoiceDiskPath(string $fileName): string
    {
        $path = rtrim((string) config('core-nation.invoice_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return $path.$fileName;
    }

    public function invoicePublicUrl(string $fileName): string
    {
        return rtrim((string) config('core-nation.invoice_url'), '/').'/'.$fileName;
    }

    public function invoicePdfExists(Transaction $transaction): bool
    {
        return File::exists($this->invoiceDiskPath($this->invoiceFileName($transaction)));
    }

    public function ensureInvoicePdf(Transaction $transaction): string
    {
        $transaction->loadMissing(['details.item.group', 'sender', 'receiver', 'user']);

        $fileName = $this->invoiceFileName($transaction);
        $filePath = $this->invoiceDiskPath($fileName);

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $typeLabel = $transaction->getTypeLabel();

        Pdf::loadView('transactions.pdf.invoice', compact('transaction', 'typeLabel'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ])
            ->save($filePath);

        return $this->invoicePublicUrl($fileName);
    }
}
