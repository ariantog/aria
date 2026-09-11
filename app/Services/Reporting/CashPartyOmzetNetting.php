<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class CashPartyOmzetNetting
{
    /**
     * Trade parties whose Cash In to an entity bank may be reduced by Cash Out
     * back to the same party (same month or a later month, FIFO).
     *
     * @return list<int>
     */
    public static function nettingPartyTypes(): array
    {
        return [
            Addrbook::TYPE_CUSTOMER,
            Addrbook::TYPE_RESELLER,
            Addrbook::TYPE_SUPPLIER,
        ];
    }

    private ?string $allocationCacheKey = null;

    /**
     * @var array<string, array<string, array{
     *     cash_in_gross: float,
     *     cash_out_allocated: float,
     *     net_remaining: float,
     * }>>
     */
    private array $allocationsByEntityParty = [];

    /**
     * @param  list<int>  $nonPkpEntityIds
     * @return Collection<int, array{
     *     party_id: int,
     *     party: string,
     *     entity_id: int,
     *     entity_name: string,
     *     cash_in_gross: float,
     *     cash_out_gross: float,
     *     net_omzet: float,
     *     pph_final: float,
     * }>
     */
    public function netRows(int $year, int $month, array $nonPkpEntityIds, ?\DateTimeInterface $asOf = null): Collection
    {
        if ($nonPkpEntityIds === []) {
            return collect();
        }

        $rate = (float) config('reporting.pph_final_rate', 0.005);
        $this->ensureAllocations($nonPkpEntityIds, $asOf);
        $monthKey = sprintf('%04d-%02d', $year, $month);

        return collect($this->allocationsByEntityParty)
            ->map(function (array $months, string $entityPartyKey) use ($monthKey, $rate) {
                $monthSlice = $months[$monthKey] ?? null;
                if ($monthSlice === null || $monthSlice['cash_in_gross'] <= 0) {
                    return null;
                }

                [$entityId, $partyId] = array_map('intval', explode(':', $entityPartyKey, 2));
                $entity = ReportingEntity::query()->find($entityId);

                return [
                    'party_id' => $partyId,
                    'party' => $this->partyName($partyId),
                    'entity_id' => $entityId,
                    'entity_name' => $entity?->name ?? 'Entitas',
                    'cash_in_gross' => round($monthSlice['cash_in_gross'], 2),
                    'cash_out_gross' => round($monthSlice['cash_out_allocated'], 2),
                    'net_omzet' => round($monthSlice['net_remaining'], 2),
                    'pph_final' => round($monthSlice['net_remaining'] * $rate, 2),
                ];
            })
            ->filter()
            ->sortBy([
                ['entity_name', 'asc'],
                ['party', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  list<int>  $nonPkpEntityIds
     */
    public function totalPphFinal(int $year, int $month, array $nonPkpEntityIds, ?\DateTimeInterface $asOf = null): float
    {
        return round((float) $this->netRows($year, $month, $nonPkpEntityIds, $asOf)->sum('pph_final'), 2);
    }

    /**
     * @param  list<int>  $nonPkpEntityIds
     */
    private function ensureAllocations(array $nonPkpEntityIds, ?\DateTimeInterface $asOf = null): void
    {
        sort($nonPkpEntityIds);
        $cacheKey = implode(',', $nonPkpEntityIds).'|'.ReportingPeriod::queryEnd($asOf ?? now());

        if ($this->allocationCacheKey === $cacheKey) {
            return;
        }

        $this->allocationCacheKey = $cacheKey;
        $this->allocationsByEntityParty = $this->buildAllocations($nonPkpEntityIds, $asOf);
    }

    /**
     * @param  list<int>  $nonPkpEntityIds
     * @return array<string, array<string, array{
     *     cash_in_gross: float,
     *     cash_out_allocated: float,
     *     net_remaining: float,
     * }>>
     */
    private function buildAllocations(array $nonPkpEntityIds, ?\DateTimeInterface $asOf = null): array
    {
        $rangeStart = ReportingPeriod::monthStart(PphFinalReportService::MIN_YEAR, 1)->toDateString();
        $rangeEnd = ReportingPeriod::queryEnd($asOf ?? now());

        /** @var array<string, list<array{
         *     year: int,
         *     month: int,
         *     gross: float,
         *     remaining: float,
         *     allocated_out: float,
         * }>> $depositBuckets
         */
        $depositBuckets = [];

        $cashIns = Transaction::query()
            ->countsInReporting()
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->where('sender_type', '!=', Addrbook::TYPE_ACCOUNT)
            ->whereIn('sender_type', self::nettingPartyTypes())
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'sender_id', 'receiver_id', 'total', 'ppn']);

        foreach ($cashIns as $transaction) {
            if ((float) $transaction->ppn > 0) {
                continue;
            }

            $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);
            if (! $entity || $entity->is_pkp || ! in_array($entity->id, $nonPkpEntityIds, true)) {
                continue;
            }

            $key = $this->entityPartyKey($entity->id, (int) $transaction->sender_id);
            $depositBuckets[$key][] = [
                'year' => (int) $transaction->date->year,
                'month' => (int) $transaction->date->month,
                'gross' => abs((float) $transaction->total),
                'remaining' => abs((float) $transaction->total),
                'allocated_out' => 0.0,
            ];
        }

        $cashOuts = Transaction::query()
            ->countsInReporting()
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('sender_type', Addrbook::TYPE_BANK)
            ->whereIn('receiver_type', self::nettingPartyTypes())
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'sender_id', 'receiver_id', 'total']);

        foreach ($cashOuts as $transaction) {
            $entity = ReportingEntity::findActiveForBank((int) $transaction->sender_id);
            if (! $entity || $entity->is_pkp || ! in_array($entity->id, $nonPkpEntityIds, true)) {
                continue;
            }

            $key = $this->entityPartyKey($entity->id, (int) $transaction->receiver_id);
            if (! isset($depositBuckets[$key])) {
                continue;
            }

            $remainingRefund = abs((float) $transaction->total);

            foreach ($depositBuckets[$key] as &$bucket) {
                if ($remainingRefund <= 0) {
                    break;
                }

                if ($bucket['remaining'] <= 0) {
                    continue;
                }

                $applied = min($remainingRefund, $bucket['remaining']);
                $bucket['remaining'] -= $applied;
                $bucket['allocated_out'] += $applied;
                $remainingRefund -= $applied;
            }

            unset($bucket);
        }

        /** @var array<string, array<string, array{cash_in_gross: float, cash_out_allocated: float, net_remaining: float}>> $allocations */
        $allocations = [];

        foreach ($depositBuckets as $key => $buckets) {
            foreach ($buckets as $bucket) {
                $monthKey = sprintf('%04d-%02d', $bucket['year'], $bucket['month']);

                if (! isset($allocations[$key][$monthKey])) {
                    $allocations[$key][$monthKey] = [
                        'cash_in_gross' => 0.0,
                        'cash_out_allocated' => 0.0,
                        'net_remaining' => 0.0,
                    ];
                }

                $allocations[$key][$monthKey]['cash_in_gross'] += $bucket['gross'];
                $allocations[$key][$monthKey]['cash_out_allocated'] += $bucket['allocated_out'];
                $allocations[$key][$monthKey]['net_remaining'] += $bucket['remaining'];
            }
        }

        return $allocations;
    }

    private function entityPartyKey(int $entityId, int $partyId): string
    {
        return $entityId.':'.$partyId;
    }

    private function partyName(int $addrbookId): string
    {
        return Addrbook::withTrashed()->find($addrbookId)?->name ?? '—';
    }
}
