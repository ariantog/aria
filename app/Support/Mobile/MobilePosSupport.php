<?php

namespace App\Support\Mobile;

use App\Models\Addrbook;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Services\TransactionInvoiceService;

class MobilePosSupport
{
    /**
     * @return array{show: bool, message: ?string}
     */
    public static function jubelioReminder(Transaction $transaction): array
    {
        if (! config('services.jubelio.active')) {
            return ['show' => false, 'message' => null];
        }

        $warehouseId = (int) $transaction->sender_id;
        if ($warehouseId <= 0) {
            return ['show' => false, 'message' => null];
        }

        $mapped = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('jubelio_store_id', '>', 0)
            ->exists();

        if (! $mapped) {
            return ['show' => false, 'message' => null];
        }

        return [
            'show' => true,
            'message' => 'Stok sudah terpotong di Aria. Push ke Jubelio dari halaman transaksi di web jika channel online membutuhkan sync — mobile tidak melakukan reserve atau push otomatis.',
        ];
    }

    public static function whatsappLink(
        Transaction $transaction,
        string $phone,
        TransactionInvoiceService $invoiceService,
    ): string {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $fileUrl = $invoiceService->ensureInvoicePdf($transaction);
        $text = urlencode("Terimakasih telah belanja di CoreNation! Berikut invoice anda:\n\n{$fileUrl}");

        return 'https://wa.me/'.$digits.'?text='.$text;
    }

    /**
     * @return array<string, mixed>
     */
    public static function posExtras(
        Transaction $transaction,
        ?string $whatsappPhone,
        TransactionInvoiceService $invoiceService,
    ): array {
        $invoiceService->createReceiptPdf($transaction);

        $receiptPath = '/api/mobile/v1/transactions/'.$transaction->id.'/receipt';

        $whatsapp = null;
        if ($whatsappPhone !== null && $whatsappPhone !== '') {
            $normalized = preg_replace('/\D/', '', $whatsappPhone) ?? '';
            if (strlen($normalized) >= 8 && strlen($normalized) <= 15) {
                $whatsapp = [
                    'phone' => $normalized,
                    'url' => self::whatsappLink($transaction, $normalized, $invoiceService),
                ];
            }
        }

        return [
            'receipt_url' => $receiptPath,
            'whatsapp' => $whatsapp,
            'jubelio_reminder' => self::jubelioReminder($transaction),
        ];
    }

    /**
     * @return array{id: int, name: string, ppn: bool, ppn_included: bool}|null
     */
    public static function addrbookSummary(?Addrbook $addrbook): ?array
    {
        if (! $addrbook) {
            return null;
        }

        return [
            'id' => $addrbook->id,
            'name' => $addrbook->name,
            'ppn' => (bool) $addrbook->ppn,
            'ppn_included' => $addrbook->resolvedPpnIncluded(),
        ];
    }
}
