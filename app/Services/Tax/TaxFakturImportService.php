<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxFakturImportService
{
    public function __construct(
        private readonly ExpectedPaymentDateCalculator $paymentDates,
    ) {}

    public function storeFromParsed(
        ParsedFakturPajak $parsed,
        array $data,
        ?string $pdfPath = null,
    ): TaxFakturImport {
        if (TaxFakturImport::query()->where('faktur_number', $parsed->fakturNumber)->exists()) {
            throw new InvalidArgumentException('Faktur number already imported: '.$parsed->fakturNumber);
        }

        $direction = $data['direction'];
        if (! in_array($direction, [TaxFakturImport::DIRECTION_KELUARAN, TaxFakturImport::DIRECTION_MASUKAN], true)) {
            throw new InvalidArgumentException('Invalid faktur direction.');
        }

        $counterparty = Addrbook::query()->findOrFail($data['counterparty_id']);
        $fakturDate = $parsed->fakturDate ?? now();
        $expectedPaymentDate = $this->paymentDates->fromFakturDate(
            $fakturDate,
            $counterparty->payment_due_day !== null ? (int) $counterparty->payment_due_day : null,
        );

        $paymentReceivedAmount = isset($data['payment_received_amount'])
            ? (float) $data['payment_received_amount']
            : null;
        $paymentVariance = null;
        if ($paymentReceivedAmount !== null) {
            $paymentVariance = round($paymentReceivedAmount - $parsed->grossIncludingTax(), 2);
        }

        return DB::transaction(function () use (
            $parsed,
            $data,
            $pdfPath,
            $direction,
            $counterparty,
            $fakturDate,
            $expectedPaymentDate,
            $paymentReceivedAmount,
            $paymentVariance,
        ) {
            return TaxFakturImport::create([
                'faktur_number' => $parsed->fakturNumber,
                'faktur_date' => $fakturDate,
                'faktur_date_place' => $parsed->fakturDatePlace,
                'direction' => $direction,
                'reporting_entity_id' => $data['reporting_entity_id'],
                'counterparty_id' => $counterparty->id,
                'seller_name' => $parsed->sellerName,
                'seller_npwp' => $parsed->sellerNpwp,
                'buyer_name' => $parsed->buyerName,
                'buyer_npwp' => $parsed->buyerNpwp,
                'gross_total' => $parsed->grossTotal,
                'discount_total' => $parsed->discountTotal,
                'dpp' => $parsed->dpp,
                'ppn' => $parsed->ppn,
                'ppnbm' => $parsed->ppnbm,
                'report_year' => (int) $fakturDate->year,
                'report_month' => (int) $fakturDate->month,
                'expected_payment_date' => $expectedPaymentDate,
                'payment_received_amount' => $paymentReceivedAmount,
                'payment_received_date' => $data['payment_received_date'] ?? null,
                'payment_variance' => $paymentVariance,
                'variance_expense_addrbook_id' => $data['variance_expense_addrbook_id'] ?? null,
                'signatory_name' => $parsed->signatoryName,
                'source_format' => $parsed->sourceFormat,
                'line_items' => $parsed->lineItems,
                'pdf_path' => $pdfPath,
                'notes' => $data['notes'] ?? null,
                'user_id' => Auth::id(),
            ]);
        });
    }

    public function recordPayment(
        TaxFakturImport $import,
        float $amount,
        ?string $date = null,
        ?int $cashInTransactionId = null,
    ): TaxFakturImport {
        $import->payment_received_amount = $amount;
        $import->payment_received_date = $date ?? now()->toDateString();
        $import->payment_variance = round($amount - $import->fakturGross(), 2);
        if ($cashInTransactionId) {
            $import->cash_in_transaction_id = $cashInTransactionId;
        }
        $import->save();

        return $import->fresh();
    }
}
