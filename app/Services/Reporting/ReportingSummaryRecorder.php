<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingOperationMonthlySummary;
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
}
