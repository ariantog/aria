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
     * back to the same party in the same month (consignment / pass-through).
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
    public function netRows(int $year, int $month, array $nonPkpEntityIds): Collection
    {
        if ($nonPkpEntityIds === []) {
            return collect();
        }

        [$startDate, $endDate] = ReportingPeriod::monthQueryRange($year, $month);
        $rate = (float) config('reporting.pph_final_rate', 0.005);

        /** @var array<string, array{entity: ReportingEntity, party_id: int, cash_in_gross: float, cash_out_gross: float}> $buckets */
        $buckets = [];

        $cashIns = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
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

            $this->accumulate($buckets, $entity, (int) $transaction->sender_id, 'cash_in_gross', abs((float) $transaction->total));
        }

        $cashOuts = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
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

            $this->accumulate($buckets, $entity, (int) $transaction->receiver_id, 'cash_out_gross', abs((float) $transaction->total));
        }

        return collect($buckets)
            ->map(function (array $bucket) use ($rate) {
                $net = max(0, $bucket['cash_in_gross'] - $bucket['cash_out_gross']);

                return [
                    'party_id' => $bucket['party_id'],
                    'party' => $this->partyName($bucket['party_id']),
                    'entity_id' => $bucket['entity']->id,
                    'entity_name' => $bucket['entity']->name,
                    'cash_in_gross' => round($bucket['cash_in_gross'], 2),
                    'cash_out_gross' => round($bucket['cash_out_gross'], 2),
                    'net_omzet' => round($net, 2),
                    'pph_final' => round($net * $rate, 2),
                ];
            })
            ->filter(fn (array $row) => $row['cash_in_gross'] > 0)
            ->sortBy([
                ['entity_name', 'asc'],
                ['party', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  list<int>  $nonPkpEntityIds
     */
    public function totalPphFinal(int $year, int $month, array $nonPkpEntityIds): float
    {
        return round((float) $this->netRows($year, $month, $nonPkpEntityIds)->sum('pph_final'), 2);
    }

    /**
     * @param  array<string, array{entity: ReportingEntity, party_id: int, cash_in_gross: float, cash_out_gross: float}>  $buckets
     */
    private function accumulate(array &$buckets, ReportingEntity $entity, int $partyId, string $column, float $amount): void
    {
        $key = $entity->id.':'.$partyId;

        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'entity' => $entity,
                'party_id' => $partyId,
                'cash_in_gross' => 0.0,
                'cash_out_gross' => 0.0,
            ];
        }

        $buckets[$key][$column] += $amount;
    }

    private function partyName(int $addrbookId): string
    {
        return Addrbook::withTrashed()->find($addrbookId)?->name ?? '—';
    }
}
