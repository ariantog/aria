<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FakturCashInMatcher
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     invoice: string|null,
     *     total: float,
     *     bank_name: string,
     *     score: int,
     * }>
     */
    public function suggest(
        int $counterpartyId,
        ?int $reportingEntityId = null,
        ?float $amount = null,
        ?string $date = null,
        ?string $fakturNumber = null,
        ?int $excludeImportId = null,
        int $limit = 10,
    ): Collection {
        $entityBankIds = $this->entityBankIds($reportingEntityId);
        $linkedCashInIds = $this->linkedCashInIds($excludeImportId);

        $candidates = Transaction::query()
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('sender_id', $counterpartyId)
            ->whereIn('sender_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->when($linkedCashInIds !== [], fn ($query) => $query->whereNotIn('id', $linkedCashInIds))
            ->when($date, function ($query) use ($date) {
                $from = Carbon::parse($date)->subDays(60)->toDateString();
                $to = Carbon::parse($date)->addDays(14)->toDateString();

                $query->whereBetween('date', [$from, $to]);
            }, fn ($query) => $query->where('date', '>=', now()->subMonths(6)->toDateString()))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $bankNames = Addrbook::withTrashed()
            ->whereIn('id', $candidates->pluck('receiver_id')->unique())
            ->pluck('name', 'id');

        return $candidates
            ->map(function (Transaction $transaction) use ($entityBankIds, $amount, $date, $fakturNumber, $bankNames) {
                $score = 0;
                $bankId = (int) $transaction->receiver_id;

                if ($entityBankIds !== [] && in_array($bankId, $entityBankIds, true)) {
                    $score += 100;
                }

                if ($amount !== null && abs(abs((float) $transaction->total) - abs($amount)) < 0.02) {
                    $score += 50;
                }

                if ($fakturNumber && $transaction->invoice && strcasecmp($transaction->invoice, $fakturNumber) === 0) {
                    $score += 30;
                }

                if ($date && $transaction->date?->toDateString() === $date) {
                    $score += 20;
                } elseif ($date) {
                    $daysApart = abs(Carbon::parse($date)->diffInDays($transaction->date));
                    if ($daysApart <= 7) {
                        $score += 10;
                    }
                }

                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date->toDateString(),
                    'invoice' => $transaction->invoice,
                    'total' => abs((float) $transaction->total),
                    'bank_name' => $bankNames[$bankId] ?? '—',
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * @return list<int>
     */
    private function entityBankIds(?int $reportingEntityId): array
    {
        if (! $reportingEntityId) {
            return [];
        }

        $entity = ReportingEntity::query()->with('banks')->find($reportingEntityId);
        if (! $entity) {
            return [];
        }

        return $entity->banks
            ->filter(fn ($bank) => (bool) ($bank->pivot->is_active ?? true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function linkedCashInIds(?int $excludeImportId): array
    {
        return TaxFakturImport::query()
            ->whereNotNull('cash_in_transaction_id')
            ->when($excludeImportId, fn ($query) => $query->where('id', '!=', $excludeImportId))
            ->pluck('cash_in_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
