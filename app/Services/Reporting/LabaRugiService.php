<?php

namespace App\Services\Reporting;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabaRugiService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public const PERIODS = [1, 3, 6, 12];

    /**
     * @var array<string, string>
     */
    public const SLUG_LABELS = [
        'marketplace' => 'Biaya Marketplace',
        'toko' => 'Biaya Toko',
        'penyesuaian' => 'Penyesuaian',
        'marketing' => 'Marketing',
        'gaji' => 'Gaji & Upah',
        'sewa' => 'Sewa',
        'kantor' => 'Kantor & Utilitas',
        'bank' => 'Perbankan',
        'maintenance' => 'Perawatan & Mesin',
        'jasa' => 'Jasa Profesional',
        'logistik' => 'Logistik',
        'pajak' => 'Pajak & Retribusi',
        'lain' => 'Lain-lain',
        'sdm' => 'Kesejahteraan Karyawan',
        'produksi' => 'Produksi (non-HPP)',
    ];

    public function __construct(
        private readonly InventoryRollForwardService $inventory,
        private readonly ReportingSummaryRecorder $recorder,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     months: int,
     *     period_start: string,
     *     period_end: string,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     month_keys: list<string>,
     *     month_labels: array<string, string>,
     *     pendapatan: array<string, float>,
     *     pendapatan_total: float,
     *     internal_lending_excluded: array<string, float>,
     *     internal_lending_total: float,
     *     hpp: array<string, float>,
     *     hpp_total: float,
     *     laba_kotor: array<string, float>,
     *     laba_kotor_total: float,
     *     beban: list<array{slug: string, label: string, months: array<string, float>, total: float}>,
     *     beban_total: array<string, float>,
     *     beban_grand_total: float,
     *     laba_usaha: array<string, float>,
     *     laba_usaha_total: float,
     *     pajak: array<string, float>,
     *     pajak_total: float,
     *     laba_bersih: array<string, float>,
     *     laba_bersih_total: float,
     *     drilldown: array{
     *         pendapatan: list<array<string, mixed>>,
     *         beban: list<array<string, mixed>>
     *     },
     * }
     */
    public function build(int $year, int $month, int $months, ?int $entityId): array
    {
        $months = $this->normalizePeriod($months);
        $periodMonths = ReportingPeriod::monthsEnding($year, $month, $months);
        [$periodStart, $periodEnd] = ReportingPeriod::spanRange($periodMonths);
        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;
        $entityIds = $this->resolveEntityIds($resolvedEntityId);
        $monthKeys = array_map(fn (array $pair) => $this->monthKey($pair[0], $pair[1]), $periodMonths);
        $zeroMonths = array_fill_keys($monthKeys, 0.0);

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthLabels = [];
        foreach ($periodMonths as [$y, $m]) {
            $monthLabels[$this->monthKey($y, $m)] = $monthNames[$m].' '.$y;
        }

        $lendingByMonth = $this->internalLendingCashInByMonth($periodStart, $periodEnd, $isConsolidated ? null : $resolvedEntityId);
        $pendapatan = $this->pendapatanByMonth($periodMonths, $entityIds, $lendingByMonth, $zeroMonths);
        $internalLending = array_replace($zeroMonths, $lendingByMonth);
        $hpp = $this->hppByMonth($periodMonths, $isConsolidated, $zeroMonths);
        $bebanLines = $this->bebanLines($periodMonths, $isConsolidated, $resolvedEntityId, $zeroMonths);
        $pajak = $this->pajakByMonth($periodMonths, $entityIds, $zeroMonths);

        $pendapatanTotal = array_sum($pendapatan);
        $hppTotal = array_sum($hpp);
        $internalLendingTotal = array_sum($internalLending);
        $pajakTotal = array_sum($pajak);

        $labaKotor = $zeroMonths;
        $bebanTotal = $zeroMonths;
        $labaUsaha = $zeroMonths;
        $labaBersih = $zeroMonths;

        foreach ($monthKeys as $key) {
            $labaKotor[$key] = $pendapatan[$key] - $hpp[$key];
        }

        foreach ($bebanLines as $line) {
            foreach ($monthKeys as $key) {
                $bebanTotal[$key] += $line['months'][$key];
            }
        }

        foreach ($monthKeys as $key) {
            $labaUsaha[$key] = $labaKotor[$key] - $bebanTotal[$key];
            $labaBersih[$key] = $labaUsaha[$key] - $pajak[$key];
        }

        return [
            'year' => $year,
            'month' => $month,
            'months' => $months,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'month_keys' => $monthKeys,
            'month_labels' => $monthLabels,
            'pendapatan' => $pendapatan,
            'pendapatan_total' => $pendapatanTotal,
            'internal_lending_excluded' => $internalLending,
            'internal_lending_total' => $internalLendingTotal,
            'hpp' => $hpp,
            'hpp_total' => $hppTotal,
            'laba_kotor' => $labaKotor,
            'laba_kotor_total' => $pendapatanTotal - $hppTotal,
            'beban' => $bebanLines,
            'beban_total' => $bebanTotal,
            'beban_grand_total' => array_sum($bebanTotal),
            'laba_usaha' => $labaUsaha,
            'laba_usaha_total' => array_sum($labaUsaha),
            'pajak' => $pajak,
            'pajak_total' => $pajakTotal,
            'laba_bersih' => $labaBersih,
            'laba_bersih_total' => array_sum($labaBersih),
            'drilldown' => [
                'pendapatan' => $this->pendapatanRows($periodStart, $periodEnd, $isConsolidated ? null : $resolvedEntityId),
                'beban' => $this->bebanRows($periodStart, $periodEnd, $isConsolidated, $resolvedEntityId),
            ],
        ];
    }

    public function exportCsv(array $report): StreamedResponse
    {
        $filename = sprintf(
            'laba-rugi-%s-%04d-%02d-%dmo.csv',
            str($report['entity_label'])->slug(),
            $report['year'],
            $report['month'],
            $report['months'],
        );

        return new StreamedResponse(function () use ($report) {
            $out = fopen('php://output', 'w');
            $monthKeys = $report['month_keys'];
            $header = array_merge(
                ['Laporan Laba Rugi', $report['entity_label'], $report['period_start'].' — '.$report['period_end']],
                [],
            );
            fputcsv($out, $header);
            fputcsv($out, array_merge(['Akun'], array_map(fn ($key) => $report['month_labels'][$key], $monthKeys), ['Total']));

            $write = function (string $label, array $months, float $total) use ($out, $monthKeys) {
                fputcsv($out, array_merge([$label], array_map(fn ($key) => $months[$key] ?? 0, $monthKeys), [$total]));
            };

            $write('Pendapatan usaha', $report['pendapatan'], $report['pendapatan_total']);
            $write('HPP (roll-forward)', $report['hpp'], $report['hpp_total']);
            $write('Laba kotor', $report['laba_kotor'], $report['laba_kotor_total']);
            foreach ($report['beban'] as $line) {
                $write($line['label'], $line['months'], $line['total']);
            }
            $write('Jumlah beban usaha', $report['beban_total'], $report['beban_grand_total']);
            $write('Laba usaha', $report['laba_usaha'], $report['laba_usaha_total']);
            $write('Beban pajak', $report['pajak'], $report['pajak_total']);
            $write('Laba bersih', $report['laba_bersih'], $report['laba_bersih_total']);

            if ($report['internal_lending_total'] > 0) {
                fputcsv($out, []);
                $write('Internal lending (excluded)', $report['internal_lending_excluded'], $report['internal_lending_total']);
            }

            fputcsv($out, []);
            fputcsv($out, ['Drill-down pendapatan']);
            fputcsv($out, ['Tanggal', 'Invoice', 'Pihak', 'Entitas', 'Jumlah', 'Ref']);
            foreach ($report['drilldown']['pendapatan'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['invoice'],
                    $row['party'],
                    $row['entity_name'],
                    $row['amount'],
                    'tx:'.$row['id'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Drill-down beban']);
            fputcsv($out, ['Tanggal', 'Kategori', 'Ledger', 'Entitas', 'Jumlah', 'Ref']);
            foreach ($report['drilldown']['beban'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['label'],
                    $row['party'],
                    $row['entity_name'],
                    $row['amount'],
                    'tx:'.$row['id'],
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function yearOptions(?\DateTimeInterface $now = null): array
    {
        $currentYear = (int) Carbon::parse($now ?? now())->year;
        $start = min(self::MIN_YEAR, $currentYear);

        return range($currentYear, $start);
    }

    public function entityLabel(?int $entityId): string
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return 'Konsolidasi';
        }

        return ReportingEntity::query()->find($entityId)?->name ?? 'Entitas';
    }

    public function slugLabel(string $slug): string
    {
        return self::SLUG_LABELS[$slug] ?? str($slug)->replace('_', ' ')->title()->toString();
    }

    public function normalizePeriod(int $months): int
    {
        return in_array($months, self::PERIODS, true) ? $months : 1;
    }

    /**
     * @return list<int>
     */
    private function resolveEntityIds(int $entityId): array
    {
        if ($entityId === self::CONSOLIDATED_ENTITY) {
            return ReportingEntity::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $exists = ReportingEntity::query()->whereKey($entityId)->exists();

        return $exists ? [$entityId] : [];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $periodMonths
     * @param  list<int>  $entityIds
     * @param  array<string, float>  $lendingByMonth
     * @param  array<string, float>  $zeroMonths
     * @return array<string, float>
     */
    private function pendapatanByMonth(array $periodMonths, array $entityIds, array $lendingByMonth, array $zeroMonths): array
    {
        $pendapatan = $zeroMonths;
        if ($entityIds === []) {
            return $pendapatan;
        }

        $rows = ReportingEntityMonthlySummary::query()
            ->where(function ($query) use ($periodMonths) {
                foreach ($periodMonths as [$year, $month]) {
                    $query->orWhere(fn ($inner) => $inner->where('year', $year)->where('month', $month));
                }
            })
            ->whereIn('reporting_entity_id', $entityIds)
            ->get(['year', 'month', 'cash_in']);

        foreach ($rows as $row) {
            $key = $this->monthKey((int) $row->year, (int) $row->month);
            $pendapatan[$key] = ($pendapatan[$key] ?? 0) + (float) $row->cash_in;
        }

        foreach ($pendapatan as $key => $amount) {
            $pendapatan[$key] = max(0, $amount - ($lendingByMonth[$key] ?? 0));
        }

        return $pendapatan;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $periodMonths
     * @param  array<string, float>  $zeroMonths
     * @return array<string, float>
     */
    private function hppByMonth(array $periodMonths, bool $isConsolidated, array $zeroMonths): array
    {
        $hpp = $zeroMonths;
        if (! $isConsolidated) {
            return $hpp;
        }

        [$endYear, $endMonth] = $periodMonths[array_key_last($periodMonths)];
        if ($this->inventory->isBeforeStart($endYear, $endMonth)) {
            return $hpp;
        }

        $rows = $this->inventory->ensureThrough($endYear, $endMonth);
        $wanted = array_flip(array_map(fn (array $pair) => $this->monthKey($pair[0], $pair[1]), $periodMonths));

        foreach ($rows as $row) {
            $key = $this->monthKey((int) $row['year'], (int) $row['month']);
            if (! isset($wanted[$key])) {
                continue;
            }
            $hpp[$key] = (float) ($row['cogs'] ?? 0);
        }

        return $hpp;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $periodMonths
     * @param  array<string, float>  $zeroMonths
     * @return list<array{slug: string, label: string, months: array<string, float>, total: float}>
     */
    private function bebanLines(array $periodMonths, bool $isConsolidated, int $entityId, array $zeroMonths): array
    {
        [$periodStart, $periodEnd] = ReportingPeriod::spanRange($periodMonths);
        $excludedBySlugMonth = $this->excludedLedgerCashOutBySlugMonth($periodStart, $periodEnd, $isConsolidated ? null : $entityId);

        $bySlug = [];

        if ($isConsolidated) {
            $rows = ReportingOperationMonthlySummary::query()
                ->where(function ($query) use ($periodMonths) {
                    foreach ($periodMonths as [$year, $month]) {
                        $query->orWhere(fn ($inner) => $inner->where('year', $year)->where('month', $month));
                    }
                })
                ->get(['year', 'month', 'report_slug', 'cash_out']);

            foreach ($rows as $row) {
                $slug = (string) $row->report_slug;
                $key = $this->monthKey((int) $row->year, (int) $row->month);
                $bySlug[$slug] ??= $zeroMonths;
                $bySlug[$slug][$key] = ($bySlug[$slug][$key] ?? 0) + abs((float) $row->cash_out);
            }
        } else {
            foreach ($this->operationCashOutRows($periodStart, $periodEnd, $entityId) as $row) {
                $slug = $row['slug'];
                $key = $row['month_key'];
                $bySlug[$slug] ??= $zeroMonths;
                $bySlug[$slug][$key] = ($bySlug[$slug][$key] ?? 0) + $row['amount'];
            }
        }

        foreach ($excludedBySlugMonth as $slug => $months) {
            foreach ($months as $key => $amount) {
                if (! isset($bySlug[$slug])) {
                    continue;
                }
                $bySlug[$slug][$key] = max(0, ($bySlug[$slug][$key] ?? 0) - $amount);
            }
        }

        $lines = [];
        foreach ($bySlug as $slug => $months) {
            $total = array_sum($months);
            if ($total < 0.01) {
                continue;
            }
            $lines[] = [
                'slug' => $slug,
                'label' => $this->slugLabel($slug),
                'months' => array_replace($zeroMonths, $months),
                'total' => $total,
            ];
        }

        usort($lines, fn (array $a, array $b) => $a['label'] <=> $b['label']);

        return $lines;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $periodMonths
     * @param  list<int>  $entityIds
     * @param  array<string, float>  $zeroMonths
     * @return array<string, float>
     */
    private function pajakByMonth(array $periodMonths, array $entityIds, array $zeroMonths): array
    {
        $pajak = $zeroMonths;
        if ($entityIds === []) {
            return $pajak;
        }

        $rows = ReportingMonthlyTaxSummary::query()
            ->where(function ($query) use ($periodMonths) {
                foreach ($periodMonths as [$year, $month]) {
                    $query->orWhere(fn ($inner) => $inner->where('year', $year)->where('month', $month));
                }
            })
            ->whereIn('reporting_entity_id', $entityIds)
            ->get(['year', 'month', 'pph_final', 'tax_paid']);

        foreach ($rows as $row) {
            $key = $this->monthKey((int) $row->year, (int) $row->month);
            $pajak[$key] = ($pajak[$key] ?? 0)
                + abs((float) $row->pph_final)
                + abs((float) $row->tax_paid);
        }

        return $pajak;
    }

    /**
     * @return array<string, float>
     */
    private function internalLendingCashInByMonth(string $start, string $end, ?int $entityId): array
    {
        $lendingIds = $this->internalLendingContactIds();
        if ($lendingIds === []) {
            return [];
        }

        $bankIds = $this->entityBankIds($entityId);
        if ($bankIds === []) {
            return [];
        }

        $rows = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->where('sender_type', '!=', Addrbook::TYPE_ACCOUNT)
            ->whereIn('sender_id', $lendingIds)
            ->whereIn('receiver_id', $bankIds)
            ->whereBetween('date', [$start, $end])
            ->get(['date', 'total']);

        $byMonth = [];
        foreach ($rows as $row) {
            $key = $this->monthKey((int) $row->date->year, (int) $row->date->month);
            $byMonth[$key] = ($byMonth[$key] ?? 0) + abs((float) $row->total);
        }

        return $byMonth;
    }

    /**
     * @return list<array{id: int, date: string, invoice: string|null, party: string, entity_name: string|null, amount: float}>
     */
    private function pendapatanRows(string $start, string $end, ?int $entityId): array
    {
        $lendingIds = $this->internalLendingContactIds();
        $bankIds = $this->entityBankIds($entityId);
        if ($bankIds === []) {
            return [];
        }

        $rows = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->where('sender_type', '!=', Addrbook::TYPE_ACCOUNT)
            ->whereIn('receiver_id', $bankIds)
            ->when($lendingIds !== [], fn ($query) => $query->whereNotIn('sender_id', $lendingIds))
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $rows->map(function (Transaction $transaction) {
            $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);

            return [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'invoice' => $transaction->invoice,
                'party' => $transaction->sender?->name ?? '—',
                'entity_name' => $entity?->name,
                'amount' => abs((float) $transaction->total),
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, date: string, slug: string, label: string, party: string, entity_name: string|null, amount: float, month_key: string}>
     */
    private function bebanRows(string $start, string $end, bool $isConsolidated, int $entityId): array
    {
        $excludedIds = $this->excludedLedgerIds();

        return $this->operationCashOutRows($start, $end, $isConsolidated ? null : $entityId)
            ->reject(fn (array $row) => in_array($row['ledger_id'], $excludedIds, true))
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'date' => $row['date'],
                'slug' => $row['slug'],
                'label' => $this->slugLabel($row['slug']),
                'party' => $row['party'],
                'entity_name' => $row['entity_name'],
                'amount' => $row['amount'],
                'month_key' => $row['month_key'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     slug: string,
     *     party: string,
     *     entity_name: string|null,
     *     amount: float,
     *     month_key: string,
     *     ledger_id: int
     * }>
     */
    private function operationCashOutRows(string $start, string $end, ?int $entityId): Collection
    {
        $bankIds = $this->entityBankIds($entityId);
        if ($bankIds === []) {
            return collect();
        }

        $rows = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('sender_type', Addrbook::TYPE_BANK)
            ->where('receiver_type', Addrbook::TYPE_ACCOUNT)
            ->whereIn('sender_id', $bankIds)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $rows->map(function (Transaction $transaction) {
            $slug = $this->recorder->resolveReportSlugForLedger((int) $transaction->receiver_id);
            if (! $slug) {
                return null;
            }

            $entity = ReportingEntity::findActiveForBank((int) $transaction->sender_id);

            return [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'slug' => $slug,
                'party' => $transaction->receiver?->name ?? '—',
                'entity_name' => $entity?->name,
                'amount' => abs((float) $transaction->total),
                'month_key' => $this->monthKey((int) $transaction->date->year, (int) $transaction->date->month),
                'ledger_id' => (int) $transaction->receiver_id,
            ];
        })->filter()->values();
    }

    /**
     * Cash-outs to material / production / tax / exclude ledgers, keyed by report slug + month.
     *
     * @return array<string, array<string, float>>
     */
    private function excludedLedgerCashOutBySlugMonth(string $start, string $end, ?int $entityId): array
    {
        $excludedIds = $this->excludedLedgerIds();
        if ($excludedIds === []) {
            return [];
        }

        $bankIds = $this->entityBankIds($entityId);
        if ($bankIds === []) {
            return [];
        }

        $rows = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('sender_type', Addrbook::TYPE_BANK)
            ->where('receiver_type', Addrbook::TYPE_ACCOUNT)
            ->whereIn('receiver_id', $excludedIds)
            ->whereIn('sender_id', $bankIds)
            ->whereBetween('date', [$start, $end])
            ->get(['receiver_id', 'date', 'total']);

        $bySlug = [];
        foreach ($rows as $row) {
            $slug = $this->recorder->resolveReportSlugForLedger((int) $row->receiver_id);
            if (! $slug) {
                continue;
            }
            $key = $this->monthKey((int) $row->date->year, (int) $row->date->month);
            $bySlug[$slug][$key] = ($bySlug[$slug][$key] ?? 0) + abs((float) $row->total);
        }

        return $bySlug;
    }

    /**
     * @return list<int>
     */
    private function excludedLedgerIds(): array
    {
        return array_values(array_unique(array_merge(
            ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::Material),
            ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::ProductionCost),
            ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::TaxPayment),
            ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::Exclude),
        )));
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
    private function entityBankIds(?int $entityId): array
    {
        $query = DB::table('reporting_entity_banks')->where('is_active', true);

        if ($entityId !== null && $entityId !== self::CONSOLIDATED_ENTITY) {
            $query->where('reporting_entity_id', $entityId);
        }

        return $query->pluck('bank_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function monthKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }
}
