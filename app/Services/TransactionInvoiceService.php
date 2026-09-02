<?php

namespace App\Services;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class TransactionInvoiceService
{
    public function __construct(
        protected InvoiceBrandingService $brandingService,
    ) {}

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

    public function invoicePdfUrl(Transaction $transaction): ?string
    {
        if (! $this->invoicePdfExists($transaction)) {
            return null;
        }

        return route('transactions.pdf.show', $transaction);
    }

    public function invoicePdfExists(Transaction $transaction): bool
    {
        return File::exists($this->invoiceDiskPath($this->invoiceFileName($transaction)));
    }

    public function deleteInvoicePdf(Transaction $transaction): void
    {
        $filePath = $this->invoiceDiskPath($this->invoiceFileName($transaction));
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    public function ensureInvoicePdf(Transaction $transaction): string
    {
        return $this->createInvoicePdf($transaction, regenerate: false);
    }

    public function createInvoicePdf(Transaction $transaction, bool $regenerate = true, ?array $itemView = null): string
    {
        $fileName = $this->invoiceFileName($transaction);
        $filePath = $this->invoiceDiskPath($fileName);

        if (File::exists($filePath) && ! $regenerate) {
            return $this->invoicePublicUrl($fileName);
        }

        $transaction->loadMissing(['details.item.group', 'sender', 'receiver', 'user']);

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $typeLabel = $transaction->getTypeLabel();
        $branding = $this->brandingService->forTransaction($transaction);
        $itemView ??= \App\Support\TransactionItemViewOptions::defaults();

        Pdf::loadView('transactions.pdf.invoice', compact('transaction', 'typeLabel', 'branding', 'itemView'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ])
            ->save($filePath);

        return $this->invoicePublicUrl($fileName);
    }

    public function receiptFileName(Transaction $transaction): string
    {
        return 'receipt_'.$transaction->id.'.pdf';
    }

    public function receiptPdfExists(Transaction $transaction): bool
    {
        return File::exists($this->invoiceDiskPath($this->receiptFileName($transaction)));
    }

    public function createReceiptPdf(Transaction $transaction, bool $regenerate = true): string
    {
        $fileName = $this->receiptFileName($transaction);
        $filePath = $this->invoiceDiskPath($fileName);

        if (File::exists($filePath) && ! $regenerate) {
            return $this->invoicePublicUrl($fileName);
        }

        $transaction->loadMissing(['details.item.group', 'sender', 'receiver', 'user']);
        $branding = $this->brandingService->forTransaction($transaction);

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        Pdf::loadView('transactions.pdf.receipt', compact('transaction', 'branding'))
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
