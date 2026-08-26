<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxFakturImportService
{
    public function __construct(
        private readonly ExpectedPaymentDateCalculator $paymentDates,
        private readonly PostFakturPaymentVariance $postVariance,
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
            $import = TaxFakturImport::create([
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
                'cash_in_transaction_id' => $this->resolveCashInTransactionId($data),
                'signatory_name' => $parsed->signatoryName,
                'source_format' => $parsed->sourceFormat,
                'line_items' => $parsed->lineItems,
                'pdf_path' => $pdfPath,
                'notes' => $data['notes'] ?? null,
                'user_id' => Auth::id(),
            ]);

            $this->postVariance->execute($import->fresh());

            return $import->fresh();
        });
    }

    public function linkCashIn(TaxFakturImport $import, ?int $cashInTransactionId): TaxFakturImport
    {
        if ($cashInTransactionId) {
            $this->assertValidCashInLink($import, $cashInTransactionId);
        }

        $import->cash_in_transaction_id = $cashInTransactionId;
        $import->save();

        return $import->fresh();
    }

    public function recordPayment(
        TaxFakturImport $import,
        float $amount,
        ?string $date = null,
        ?int $cashInTransactionId = null,
        ?int $varianceExpenseAddrbookId = null,
    ): TaxFakturImport {
        $import->payment_received_amount = $amount;
        $import->payment_received_date = $date ?? now()->toDateString();
        $import->payment_variance = round($amount - $import->fakturGross(), 2);
        if ($cashInTransactionId) {
            $this->assertValidCashInLink($import, $cashInTransactionId);
            $import->cash_in_transaction_id = $cashInTransactionId;
        }
        if ($varianceExpenseAddrbookId) {
            $import->variance_expense_addrbook_id = $varianceExpenseAddrbookId;
        }
        $import->save();

        $import = $import->fresh();
        $this->postVariance->execute($import);

        return $import->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCashInTransactionId(array $data): ?int
    {
        $cashInId = isset($data['cash_in_transaction_id']) ? (int) $data['cash_in_transaction_id'] : null;
        if (! $cashInId) {
            return null;
        }

        $cashIn = Transaction::query()->find($cashInId);
        if (! $cashIn || (int) $cashIn->type !== Transaction::TYPE_CASH_IN) {
            throw new InvalidArgumentException('Linked transaction must be a Cash In.');
        }

        if ((int) $cashIn->sender_id !== (int) $data['counterparty_id']) {
            throw new InvalidArgumentException('Cash In sender must match faktur counterparty.');
        }

        if (TaxFakturImport::query()->where('cash_in_transaction_id', $cashInId)->exists()) {
            throw new InvalidArgumentException('Cash In is already linked to another faktur import.');
        }

        return $cashInId;
    }

    private function assertValidCashInLink(TaxFakturImport $import, int $cashInTransactionId): void
    {
        $cashIn = Transaction::query()->find($cashInTransactionId);
        if (! $cashIn || (int) $cashIn->type !== Transaction::TYPE_CASH_IN) {
            throw new InvalidArgumentException('Linked transaction must be a Cash In.');
        }

        if ((int) $cashIn->sender_id !== (int) $import->counterparty_id) {
            throw new InvalidArgumentException('Cash In sender must match faktur counterparty.');
        }

        $alreadyLinked = TaxFakturImport::query()
            ->where('cash_in_transaction_id', $cashInTransactionId)
            ->where('id', '!=', $import->id)
            ->exists();

        if ($alreadyLinked) {
            throw new InvalidArgumentException('Cash In is already linked to another faktur import.');
        }
    }
}
