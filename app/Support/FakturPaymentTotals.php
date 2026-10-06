<?php

namespace App\Support;

use App\Models\TaxFakturImport;
use App\Models\Transaction;

class FakturPaymentTotals
{
    /**
     * Total pembayaran faktur (nett + PPN) = bank diterima + biaya selisih dialokasikan.
     */
    public static function paymentReceivedAmount(
        TaxFakturImport $import,
        float $bankAmount,
        ?int $varianceExpenseAccountId,
    ): float {
        $bankAmount = round(max(0, $bankAmount), 2);
        $selisih = self::selisihAmount($import, $bankAmount, $varianceExpenseAccountId);

        if ($selisih !== null && $selisih >= 0.01) {
            return round($bankAmount + $selisih, 2);
        }

        return $bankAmount;
    }

    /**
     * Selisih underpayment posted to biaya ledger (gross faktur − bank), when expense account is set.
     */
    public static function selisihAmount(
        TaxFakturImport $import,
        float $bankAmount,
        ?int $varianceExpenseAccountId,
    ): ?float {
        if (! $varianceExpenseAccountId) {
            return null;
        }

        $gross = $import->fakturGross();
        if ($bankAmount >= $gross - 0.01) {
            return null;
        }

        return round($gross - $bankAmount, 2);
    }

    public static function bankAmountFromCashIn(?Transaction $cashIn): ?float
    {
        if (! $cashIn || (int) $cashIn->type !== Transaction::TYPE_CASH_IN) {
            return null;
        }

        return round(abs((float) $cashIn->total), 2);
    }

    /**
     * Apply normalized payment_received_amount and payment_variance on the import.
     */
    public static function applyNormalizedPayment(
        TaxFakturImport $import,
        ?float $bankAmount,
        ?int $varianceExpenseAccountId = null,
    ): void {
        $expenseId = $varianceExpenseAccountId ?? $import->variance_expense_addrbook_id;

        if ($bankAmount === null) {
            if ($import->payment_received_amount === null) {
                $import->payment_variance = null;

                return;
            }

            $import->payment_variance = round((float) $import->payment_received_amount - $import->fakturGross(), 2);

            return;
        }

        $total = self::paymentReceivedAmount(
            $import,
            $bankAmount,
            $expenseId ? (int) $expenseId : null,
        );
        $import->payment_received_amount = $total;
        $import->payment_variance = round($total - $import->fakturGross(), 2);
    }
}
