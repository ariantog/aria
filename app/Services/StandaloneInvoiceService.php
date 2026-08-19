<?php

namespace App\Services;

use App\Models\StandaloneInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StandaloneInvoiceService
{
    public function __construct(
        protected InvoiceBrandingService $brandingService,
        protected InvoiceMakerSettingsService $settingsService,
    ) {}

    public function invoiceFileName(StandaloneInvoice $invoice): string
    {
        return 'standalone_invoice_'.$invoice->id.'.pdf';
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

    public function invoicePdfExists(StandaloneInvoice $invoice): bool
    {
        return File::exists($this->invoiceDiskPath($this->invoiceFileName($invoice)));
    }

    public function invoicePdfUrl(StandaloneInvoice $invoice): ?string
    {
        if (! $this->invoicePdfExists($invoice)) {
            return null;
        }

        return route('invoice-maker.pdf.show', $invoice);
    }

    public function invoicePdfDownloadUrl(StandaloneInvoice $invoice): ?string
    {
        if (! $this->invoicePdfExists($invoice)) {
            return null;
        }

        return route('invoice-maker.pdf.download', $invoice);
    }

    public function invoiceDownloadFileName(StandaloneInvoice $invoice): string
    {
        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->number) ?: 'invoice';

        return trim($safeNumber, '-').'.pdf';
    }

    public function ensureInvoicePdf(StandaloneInvoice $invoice): string
    {
        return $this->createInvoicePdf($invoice, regenerate: false);
    }

    public function createInvoicePdf(StandaloneInvoice $invoice, bool $regenerate = true): string
    {
        $fileName = $this->invoiceFileName($invoice);
        $filePath = $this->invoiceDiskPath($fileName);

        if (File::exists($filePath) && ! $regenerate) {
            return $this->invoicePublicUrl($fileName);
        }

        $invoice->loadMissing(['lines', 'sender', 'user']);
        $defaults = $this->settingsService->defaults();
        $branding = $this->brandingService->forAddrbook($invoice->sender);
        $terms = $invoice->terms_of_payment ?: $defaults['terms_of_payment'];
        $payTo = $invoice->pay_to ?: $defaults['pay_to'];
        $signatoryName = $invoice->signatory_name ?: $defaults['signatory_name'];
        $signaturePath = $defaults['signature_path'];
        $termsBullets = $this->settingsService->termsBullets($terms);
        $payToParsed = $this->settingsService->parsePayTo($payTo);

        $template = $invoice->template;
        if (! array_key_exists($template, StandaloneInvoice::TEMPLATES)) {
            $template = StandaloneInvoice::TEMPLATE_CLASSIC;
        }

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $view = 'invoice-maker.pdf.'.$template;

        Pdf::loadView($view, compact(
            'invoice',
            'branding',
            'termsBullets',
            'payToParsed',
            'signatoryName',
            'signaturePath',
        ))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ])
            ->save($filePath);

        return $this->invoicePublicUrl($fileName);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, price: float|int|string}>  $lines
     */
    public function create(array $data, array $lines, int $userId): StandaloneInvoice
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $totals = $this->calculateLineTotals($lines);

            $invoice = StandaloneInvoice::create([
                ...$data,
                'user_id' => $userId,
                'total_qty' => $totals['qty'],
                'subtotal' => $totals['subtotal'],
            ]);

            $this->syncLines($invoice, $lines);

            return $invoice->fresh(['lines', 'sender']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, price: float|int|string}>  $lines
     */
    public function update(StandaloneInvoice $invoice, array $data, array $lines): StandaloneInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $lines) {
            $totals = $this->calculateLineTotals($lines);

            $invoice->update([
                ...$data,
                'total_qty' => $totals['qty'],
                'subtotal' => $totals['subtotal'],
            ]);

            $this->syncLines($invoice, $lines);

            return $invoice->fresh(['lines', 'sender']);
        });
    }

    /**
     * @param  list<array{description: string, quantity: float|int|string, price: float|int|string}>  $lines
     * @return array{qty: float, subtotal: float}
     */
    public function calculateLineTotals(array $lines): array
    {
        $qty = 0.0;
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $lineQty = (float) ($line['quantity'] ?? 0);
            $linePrice = (float) ($line['price'] ?? 0);
            $qty += $lineQty;
            $subtotal += $lineQty * $linePrice;
        }

        return ['qty' => $qty, 'subtotal' => $subtotal];
    }

    /**
     * @param  list<array{description: string, quantity: float|int|string, price: float|int|string}>  $lines
     */
    protected function syncLines(StandaloneInvoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $price = (float) ($line['price'] ?? 0);

            $invoice->lines()->create([
                'line_order' => $index,
                'description' => trim((string) ($line['description'] ?? '')),
                'quantity' => $quantity,
                'price' => $price,
                'total' => $quantity * $price,
            ]);
        }
    }
}
