<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\ReportingTaxAccount;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class ReportingSummaryRecorder
{
    public function record(Transaction $transaction): void
    {
        if (! $this->isWithinReportingCutover($transaction)) {
            return;
        }

        $this->recordEntityCashIn($transaction);
        $this->recordOperationCashOut($transaction);
        $this->recordTax($transaction);
    }

    private function isWithinReportingCutover(Transaction $transaction): bool
    {
        $cutover = Carbon::parse(config('reporting.cutover_date'))->startOfDay();

        return $transaction->date->greaterThanOrEqualTo($cutover);
    }

    private function recordEntityCashIn(Transaction $transaction): void
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_IN) {
            return;
        }

        if ((int) $transaction->receiver_type !== Addrbook::TYPE_BANK) {
            return;
        }

        if ((int) $transaction->sender_type === Addrbook::TYPE_ACCOUNT) {
            return;
        }

        $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);
        if (! $entity) {
            return;
        }

        $summary = ReportingEntityMonthlySummary::firstOrCreate([
            'year' => $transaction->date->year,
            'month' => $transaction->date->month,
            'reporting_entity_id' => $entity->id,
        ]);

        $summary->increment('cash_in', (float) $transaction->total);
    }

    private function recordOperationCashOut(Transaction $transaction): void
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_OUT) {
            return;
        }

        if ((int) $transaction->receiver_type !== Addrbook::TYPE_ACCOUNT) {
            return;
        }

        if (ReportingTaxAccount::findForLedger((int) $transaction->receiver_id)) {
            return;
        }

        $reportSlug = $this->resolveReportSlugForLedger((int) $transaction->receiver_id);
        if (! $reportSlug) {
            return;
        }

        $summary = ReportingOperationMonthlySummary::firstOrCreate([
            'year' => $transaction->date->year,
            'month' => $transaction->date->month,
            'report_slug' => $reportSlug,
        ]);

        $summary->increment('cash_out', (float) $transaction->total);
    }

    private function recordTax(Transaction $transaction): void
    {
        $this->recordCashInTax($transaction);
        $this->recordItemTax($transaction);
        $this->recordCashTransactionTax($transaction);
        $this->recordLegacyTaxCashOut($transaction);
    }

    private function recordCashInTax(Transaction $transaction): void
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_IN) {
            return;
        }

        if ((int) $transaction->receiver_type !== Addrbook::TYPE_BANK) {
            return;
        }

        if ((int) $transaction->sender_type === Addrbook::TYPE_ACCOUNT) {
            return;
        }

        $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);
        if (! $entity) {
            return;
        }

        $summary = $this->taxSummary($transaction->date->year, $transaction->date->month, $entity->id);
        $gross = abs((float) $transaction->total);

        if ($entity->is_pkp) {
            if ((float) $transaction->ppn > 0) {
                return;
            }

            if ($this->cashInShouldInferKeluaranTax($transaction)) {
                $rate = $this->getPpnRate();
                $dpp = round($gross / (1 + $rate), 2);
                $tax = round($gross - $dpp, 2);
                $summary->increment('ppn_keluaran_dpp', $dpp);
                $summary->increment('ppn_keluaran_tax', $tax);
            }

            return;
        }

        $pphRate = (float) config('reporting.pph_final_rate', 0.005);
        $summary->increment('pph_final', round($gross * $pphRate, 2));
    }

    private function recordItemTax(Transaction $transaction): void
    {
        $taxAmount = abs((float) $transaction->ppn);
        if ($taxAmount <= 0) {
            return;
        }

        $type = (int) $transaction->type;
        $entity = match ($type) {
            Transaction::TYPE_BUY, Transaction::TYPE_RETURN_SUPPLIER => $this->resolveEntityForBuyTax($transaction),
            Transaction::TYPE_SELL, Transaction::TYPE_RETURN => $this->resolveEntityForSellTax($transaction),
            default => null,
        };

        if (! $entity) {
            return;
        }

        $dpp = abs((float) $transaction->total);
        $summary = $this->taxSummary($transaction->date->year, $transaction->date->month, $entity->id);

        match ($type) {
            Transaction::TYPE_BUY => $this->incrementTaxPair($summary, 'ppn_masukan_dpp', 'ppn_masukan_tax', $dpp, $taxAmount),
            Transaction::TYPE_SELL => $this->incrementTaxPair($summary, 'ppn_keluaran_dpp', 'ppn_keluaran_tax', $dpp, $taxAmount),
            Transaction::TYPE_RETURN => $this->incrementTaxPair($summary, 'retur_keluaran_dpp', 'retur_keluaran_tax', $dpp, $taxAmount),
            Transaction::TYPE_RETURN_SUPPLIER => $this->incrementTaxPair($summary, 'retur_masukan_dpp', 'retur_masukan_tax', $dpp, $taxAmount),
            default => null,
        };
    }

    private function recordLegacyTaxCashOut(Transaction $transaction): void
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_OUT) {
            return;
        }

        if ((int) $transaction->receiver_type !== Addrbook::TYPE_ACCOUNT) {
            return;
        }

        $taxAccount = ReportingTaxAccount::findForLedger((int) $transaction->receiver_id);
        if (! $taxAccount) {
            return;
        }

        $summary = $this->taxSummary(
            $transaction->date->year,
            $transaction->date->month,
            $taxAccount->reporting_entity_id,
        );

        $summary->increment('tax_paid', (float) $transaction->total);
    }

    public function adjustCashTransactionTax(Transaction $transaction, float $previousPpn, ?float $previousPpnDpp): void
    {
        if (! $this->isWithinReportingCutover($transaction)) {
            return;
        }

        if ($previousPpn > 0) {
            $this->applyCashTransactionTax(
                $transaction,
                $previousPpn,
                $previousPpnDpp ?? 0,
                subtract: true,
            );
        }

        if ((float) $transaction->ppn > 0) {
            $this->applyCashTransactionTax(
                $transaction,
                (float) $transaction->ppn,
                (float) ($transaction->ppn_dpp ?? 0),
                subtract: false,
            );
        }
    }

    private function recordCashTransactionTax(Transaction $transaction): void
    {
        $tax = abs((float) $transaction->ppn);
        if ($tax <= 0) {
            return;
        }

        $dpp = abs((float) ($transaction->ppn_dpp ?? 0));
        if ($dpp <= 0) {
            return;
        }

        $this->applyCashTransactionTax($transaction, $tax, $dpp, subtract: false);
    }

    private function applyCashTransactionTax(
        Transaction $transaction,
        float $tax,
        float $dpp,
        bool $subtract,
    ): void {
        $type = (int) $transaction->type;

        if (! in_array($type, [Transaction::TYPE_CASH_IN, Transaction::TYPE_CASH_OUT], true)) {
            return;
        }

        if ($type === Transaction::TYPE_CASH_OUT && ReportingTaxAccount::findForLedger((int) $transaction->receiver_id)) {
            return;
        }

        $entity = match ($type) {
            Transaction::TYPE_CASH_OUT => ReportingEntity::findActiveForBank((int) $transaction->sender_id),
            Transaction::TYPE_CASH_IN => ReportingEntity::findActiveForBank((int) $transaction->receiver_id),
            default => null,
        };

        if (! $entity?->is_pkp) {
            return;
        }

        $summary = $this->taxSummary(
            $transaction->date->year,
            $transaction->date->month,
            $entity->id,
        );

        [$dppColumn, $taxColumn] = match ($type) {
            Transaction::TYPE_CASH_OUT => ['ppn_masukan_dpp', 'ppn_masukan_tax'],
            Transaction::TYPE_CASH_IN => ['ppn_keluaran_dpp', 'ppn_keluaran_tax'],
            default => [null, null],
        };

        if ($subtract) {
            $summary->decrement($dppColumn, $dpp);
            $summary->decrement($taxColumn, $tax);

            return;
        }

        $this->incrementTaxPair($summary, $dppColumn, $taxColumn, $dpp, $tax);
    }

    public function resolveReportSlugForLedger(int $ledgerId): ?string
    {
        $canonicalId = LedgerMergeMap::resolveCanonicalCustomerId($ledgerId);
        $account = Addrbook::query()->find($canonicalId);

        if (! $account || (int) $account->type !== Addrbook::TYPE_ACCOUNT) {
            return null;
        }

        $operationId = $account->operation_id;
        if (! $operationId) {
            return null;
        }

        return Operation::query()->find($operationId)?->report_slug;
    }

    public function resolveEntityForBuyTax(Transaction $transaction): ?ReportingEntity
    {
        $payment = Transaction::query()
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('receiver_id', $transaction->sender_id)
            ->where('sender_type', Addrbook::TYPE_BANK)
            ->when(
                filled($transaction->invoice),
                fn ($query) => $query->where('invoice', $transaction->invoice),
            )
            ->orderBy('date')
            ->orderBy('id')
            ->first();

        if (! $payment) {
            return null;
        }

        return ReportingEntity::findActiveForBank((int) $payment->sender_id);
    }

    public function resolveEntityForSellTax(Transaction $transaction): ?ReportingEntity
    {
        $baseQuery = Transaction::query()
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('sender_id', $transaction->receiver_id)
            ->where('receiver_type', Addrbook::TYPE_BANK);

        $payment = null;
        if (filled($transaction->invoice)) {
            $payment = (clone $baseQuery)
                ->where('invoice', $transaction->invoice)
                ->orderBy('date')
                ->orderBy('id')
                ->first();
        }

        $payment ??= (clone $baseQuery)
            ->orderBy('date')
            ->orderBy('id')
            ->first();

        if (! $payment) {
            return null;
        }

        return ReportingEntity::findActiveForBank((int) $payment->receiver_id);
    }

    private function taxSummary(int $year, int $month, int $entityId): ReportingMonthlyTaxSummary
    {
        return ReportingMonthlyTaxSummary::firstOrCreate([
            'year' => $year,
            'month' => $month,
            'reporting_entity_id' => $entityId,
        ]);
    }

    private function incrementTaxPair(
        ReportingMonthlyTaxSummary $summary,
        string $dppColumn,
        string $taxColumn,
        float $dpp,
        float $tax,
    ): void {
        $summary->increment($dppColumn, $dpp);
        $summary->increment($taxColumn, $tax);
    }

    private function getPpnRate(): float
    {
        return (float) Setting::getValue('ppn_rate', 11) / 100;
    }

    /**
     * PKP keluaran from CashIn gross is only for non-trade payers (not customer/reseller).
     * Customer and reseller payments to an entity bank are collections; PPN keluaran
     * is tracked on Sell (ppn column) for that entity regardless of invoice #.
     */
    public function cashInShouldInferKeluaranTax(Transaction $transaction): bool
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_IN) {
            return false;
        }

        if ((int) $transaction->receiver_type !== Addrbook::TYPE_BANK) {
            return false;
        }

        if ((int) $transaction->sender_type === Addrbook::TYPE_ACCOUNT) {
            return false;
        }

        $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);
        if (! $entity || ! $entity->is_pkp) {
            return false;
        }

        return ! in_array((int) $transaction->sender_type, [
            Addrbook::TYPE_CUSTOMER,
            Addrbook::TYPE_RESELLER,
        ], true);
    }

    public function hasMatchingSellTax(Transaction $transaction): bool
    {
        if (! filled($transaction->invoice)) {
            return false;
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('invoice', $transaction->invoice)
            ->where('receiver_id', $transaction->sender_id)
            ->where('ppn', '>', 0)
            ->exists();
    }
}
