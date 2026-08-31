<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NettCashService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2019;

    /**
     * @return array{
     *     year: int,
     *     month: int|null,
     *     period_start: string,
     *     period_end: string,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     rows: list<array<string, mixed>>,
     *     lending_rows: list<array<string, mixed>>,
     *     totals: array{cash_in: float, sell: float, return: float, parties: int, customer_cash_in: float, reseller_cash_in: float},
     *     lending_total: float,
     * }
     */
    public function build(int $year, ?int $month, ?int $entityId): array
    {
        $year = max(self::MIN_YEAR, $year);
        $month = $month === null ? null : max(1, min(12, $month));
        [$periodStart, $periodEnd] = $this->periodRange($year, $month);

        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;
        $bankIds = $isConsolidated ? [] : $this->entityBankIds($resolvedEntityId);
        $lendingIds = $this->internalLendingContactIds();

        $cashIn = $this->cashInByContact($periodStart, $periodEnd, $bankIds, $isConsolidated);
        $contactIds = $cashIn->keys()->map(fn ($id) => (int) $id)->all();
        $sell = $this->absByContact($contactIds, $periodStart, $periodEnd, Transaction::TYPE_SELL, 'receiver');
        $returns = $this->absByContact($contactIds, $periodStart, $periodEnd, Transaction::TYPE_RETURN, 'sender');

        $contacts = $this->contactsById($contactIds);

        $rows = [];
        $lendingRows = [];
        $totals = [
            'cash_in' => 0.0,
            'sell' => 0.0,
            'return' => 0.0,
            'parties' => 0,
            'customer_cash_in' => 0.0,
            'reseller_cash_in' => 0.0,
        ];
        $lendingTotal = 0.0;

        foreach ($cashIn as $contactId => $agg) {
            $contactId = (int) $contactId;
            $contact = $contacts->get($contactId);
            $type = (int) ($contact?->type ?? $agg['sender_type']);
            $row = [
                'id' => $contactId,
                'name' => $contact?->name ?? 'Contact #'.$contactId,
                'type' => $type,
                'type_label' => Addrbook::typeLabel($type),
                'type_slug' => Addrbook::typeSlug($type),
                'deleted' => $contact?->trashed() ?? false,
                'cash_in' => (float) $agg['cash_in'],
                'txn_count' => (int) $agg['txn_count'],
                'sell' => (float) ($sell[$contactId] ?? 0),
                'return' => (float) ($returns[$contactId] ?? 0),
            ];

            if (in_array($contactId, $lendingIds, true)) {
                $lendingRows[] = $row;
                $lendingTotal += $row['cash_in'];

                continue;
            }

            $rows[] = $row;
            $totals['cash_in'] += $row['cash_in'];
            $totals['sell'] += $row['sell'];
            $totals['return'] += $row['return'];
            $totals['parties']++;
            if ($type === Addrbook::TYPE_RESELLER) {
                $totals['reseller_cash_in'] += $row['cash_in'];
            } else {
                $totals['customer_cash_in'] += $row['cash_in'];
            }
        }

        usort($rows, fn (array $a, array $b) => $b['cash_in'] <=> $a['cash_in'] ?: strcasecmp($a['name'], $b['name']));
        usort($lendingRows, fn (array $a, array $b) => $b['cash_in'] <=> $a['cash_in'] ?: strcasecmp($a['name'], $b['name']));

        return [
            'year' => $year,
            'month' => $month,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'rows' => $rows,
            'lending_rows' => $lendingRows,
            'totals' => $totals,
            'lending_total' => $lendingTotal,
        ];
    }

    public function exportCsv(array $report): StreamedResponse
    {
        $filename = sprintf(
            'nett-cash-%s-%04d%s.csv',
            str($report['entity_label'])->slug(),
            $report['year'],
            $report['month'] ? sprintf('-%02d', $report['month']) : '',
        );

        return new StreamedResponse(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nett Cash', $report['entity_label'], $report['period_start'].' — '.$report['period_end']]);
            fputcsv($out, ['Nama', 'Jenis', 'Cash In', 'Penjualan', 'Retur', 'Jumlah Cash In']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['type_label'],
                    $row['cash_in'],
                    $row['sell'],
                    $row['return'],
                    $row['txn_count'],
                ]);
            }
            fputcsv($out, [
                'Total bonus',
                '',
                $report['totals']['cash_in'],
                $report['totals']['sell'],
                $report['totals']['return'],
                $report['totals']['parties'],
            ]);
            if ($report['lending_rows'] !== []) {
                fputcsv($out, []);
                fputcsv($out, ['Internal lending (excluded from bonus)']);
                foreach ($report['lending_rows'] as $row) {
                    fputcsv($out, [
                        $row['name'],
                        $row['type_label'],
                        $row['cash_in'],
                        $row['sell'],
                        $row['return'],
                        $row['txn_count'],
                    ]);
                }
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return list<int>
     */
    public function yearOptions(?\DateTimeInterface $now = null): array
    {
        $currentYear = (int) Carbon::parse($now ?? now())->year;

        return range($currentYear, min(self::MIN_YEAR, $currentYear));
    }

    public function entityLabel(?int $entityId): string
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return 'Semua bank';
        }

        return ReportingEntity::query()->find($entityId)?->name ?? 'Entitas';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodRange(int $year, ?int $month): array
    {
        if ($month === null) {
            return [
                Carbon::create($year, 1, 1)->toDateString(),
                Carbon::create($year, 12, 31)->toDateString(),
            ];
        }

        return ReportingPeriod::monthRange($year, $month);
    }

    /**
     * @param  list<int>  $bankIds
     * @return Collection<int, array{sender_type: int, cash_in: float, txn_count: int}>
     */
    private function cashInByContact(string $start, string $end, array $bankIds, bool $isConsolidated): Collection
    {
        if (! $isConsolidated && $bankIds === []) {
            return collect();
        }

        $rows = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->whereIn('sender_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->when(! $isConsolidated, fn ($query) => $query->whereIn('receiver_id', $bankIds))
            ->whereBetween('date', ReportingPeriod::queryBounds($start, $end))
            ->selectRaw('sender_id, sender_type, SUM(ABS(total)) as cash_in, COUNT(*) as txn_count')
            ->groupBy('sender_id', 'sender_type')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->sender_id => [
                'sender_type' => (int) $row->sender_type,
                'cash_in' => (float) $row->cash_in,
                'txn_count' => (int) $row->txn_count,
            ],
        ]);
    }

    /**
     * @param  list<int>  $contactIds
     * @return array<int, float>
     */
    private function absByContact(array $contactIds, string $start, string $end, int $type, string $side): array
    {
        if ($contactIds === []) {
            return [];
        }

        $idColumn = $side === 'sender' ? 'sender_id' : 'receiver_id';
        $typeColumn = $side === 'sender' ? 'sender_type' : 'receiver_type';

        return Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', $type)
            ->whereIn($typeColumn, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->whereIn($idColumn, $contactIds)
            ->whereBetween('date', ReportingPeriod::queryBounds($start, $end))
            ->selectRaw($idColumn.' as contact_id, SUM(ABS(total)) as amount')
            ->groupBy($idColumn)
            ->pluck('amount', 'contact_id')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Addrbook>
     */
    private function contactsById(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Addrbook::withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'type', 'deleted_at'])
            ->keyBy('id');
    }

    /**
     * @return list<int>
     */
    private function internalLendingContactIds(): array
    {
        return Addrbook::query()
            ->withTrashed()
            ->where('is_internal_lending', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function entityBankIds(int $entityId): array
    {
        return DB::table('reporting_entity_banks')
            ->where('is_active', true)
            ->where('reporting_entity_id', $entityId)
            ->pluck('bank_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
