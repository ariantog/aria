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
            if ($this->hasMatchingSellTax($transaction)) {
                return;
            }

            $rate = $this->getPpnRate();
            $dpp = round($gross / (1 + $rate), 2);
            $tax = round($gross - $dpp, 2);
            $summary->increment('ppn_keluaran_dpp', $dpp);
            $summary->increment('ppn_keluaran_tax', $tax);

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
        $payment = Transaction::query()
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('sender_id', $transaction->receiver_id)
            ->where('receiver_type', Addrbook::TYPE_BANK)
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
