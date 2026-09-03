<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PphFinalReportService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public function __construct(
        private readonly CashPartyOmzetNetting $omzetNetting,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     rate: float,
     *     gross_cash_in: float,
     *     net_omzet: float,
     *     pph_final: float,
     *     computed_pph: float,
     *     tax_paid: float,
     *     rows: Collection<int, array<string, mixed>>,
     * }
     */
    public function build(int $year, int $month, ?int $entityId): array
    {
        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;
        $rate = (float) config('reporting.pph_final_rate', 0.005);

        if ($year < self::MIN_YEAR) {
            return $this->emptyReport($year, $month, $resolvedEntityId, $isConsolidated, $rate);
        }

        $entityIds = $this->resolveNonPkpEntityIds($resolvedEntityId);
        $rows = $entityIds === []
            ? collect()
            : $this->omzetNetting->netRows($year, $month, $entityIds);

        $totals = $this->summaryTotals($year, $month, $entityIds);
        $grossCashIn = (float) $rows->sum('cash_in_gross');
        $netOmzet = (float) $rows->sum('net_omzet');
        $computedPph = (float) $rows->sum('pph_final');

        return [
            'year' => $year,
            'month' => $month,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'rate' => $rate,
            'gross_cash_in' => $grossCashIn,
            'net_omzet' => $netOmzet,
            'pph_final' => $totals['pph_final'],
            'computed_pph' => $computedPph,
            'tax_paid' => $totals['tax_paid'],
            'rows' => $rows,
        ];
    }

    public function exportCsv(array $report): StreamedResponse
    {
        $filename = $this->filename($report, 'csv');

        return new StreamedResponse(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Laporan PPh Final', $report['entity_label'], sprintf('%04d-%02d', $report['year'], $report['month'])]);
            fputcsv($out, []);
            fputcsv($out, ['Ringkasan']);
            fputcsv($out, ['Gross CashIn', $report['gross_cash_in']]);
            fputcsv($out, ['Net Omzet (CashIn − CashOut same party)', $report['net_omzet']]);
            fputcsv($out, ['PPh Final', $report['pph_final']]);
            fputcsv($out, ['Tax Paid', $report['tax_paid']]);
            fputcsv($out, ['Rate', $report['rate']]);
            fputcsv($out, []);
            fputcsv($out, ['Net omzet per pihak']);
            fputcsv($out, ['Pihak', 'Entitas', 'Cash In', 'Cash Out', 'Net Omzet', 'PPh Final']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['party'],
                    $row['entity_name'],
                    $row['cash_in_gross'],
                    $row['cash_out_gross'],
                    $row['net_omzet'],
                    $row['pph_final'],
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportXlsx(array $report): StreamedResponse
    {
        return app(ReportingExcelExport::class)->download(
            $this->filename($report, 'xlsx'),
            'PPh Final',
            [
                [
                    'title' => 'Laporan PPh Final',
                    'rows' => [
                        ['Entitas', $report['entity_label']],
                        ['Periode', sprintf('%04d-%02d', $report['year'], $report['month'])],
                    ],
                ],
                [
                    'title' => 'Ringkasan',
                    'headers' => ['Akun', 'Jumlah'],
                    'rows' => [
                        ['Gross CashIn', $report['gross_cash_in']],
                        ['Net Omzet', $report['net_omzet']],
                        ['PPh Final', $report['pph_final']],
                        ['Tax Paid', $report['tax_paid']],
                        ['Rate', $report['rate']],
                    ],
                ],
                [
                    'title' => 'Net omzet per pihak',
                    'headers' => ['Pihak', 'Entitas', 'Cash In', 'Cash Out', 'Net Omzet', 'PPh Final'],
                    'rows' => $report['rows']->map(fn (array $row) => [
                        $row['party'],
                        $row['entity_name'],
                        $row['cash_in_gross'],
                        $row['cash_out_gross'],
                        $row['net_omzet'],
                        $row['pph_final'],
                    ])->all(),
                ],
            ],
        );
    }

    public function yearOptions(?\DateTimeInterface $now = null): array
    {
        $currentYear = (int) Carbon::parse($now ?? now())->year;
        $start = max(self::MIN_YEAR, $currentYear);

        return range($start, self::MIN_YEAR);
    }

    public function entityLabel(?int $entityId): string
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return 'Konsolidasi';
        }

        return ReportingEntity::query()->find($entityId)?->name ?? 'Entitas';
    }

    /**
     * @return Collection<int, ReportingEntity>
     */
    public function nonPkpEntities(): Collection
    {
        return ReportingEntity::query()
            ->where('is_active', true)
            ->where('is_pkp', false)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);
    }

    /**
     * @return list<int>
     */
    private function resolveNonPkpEntityIds(?int $entityId): array
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return ReportingEntity::query()
                ->where('is_active', true)
                ->where('is_pkp', false)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $entity = ReportingEntity::query()->find($entityId);
        if (! $entity || $entity->is_pkp) {
            return [];
        }

        return [(int) $entity->id];
    }

    /**
     * @param  list<int>  $entityIds
     * @return array{pph_final: float, tax_paid: float}
     */
    private function summaryTotals(int $year, int $month, array $entityIds): array
    {
        if ($entityIds === []) {
            return ['pph_final' => 0.0, 'tax_paid' => 0.0];
        }

        $row = ReportingMonthlyTaxSummary::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('reporting_entity_id', $entityIds)
            ->selectRaw('COALESCE(SUM(pph_final), 0) as pph_final, COALESCE(SUM(tax_paid), 0) as tax_paid')
            ->first();

        return [
            'pph_final' => (float) $row->pph_final,
            'tax_paid' => (float) $row->tax_paid,
        ];
    }

    private function filename(array $report, string $extension): string
    {
        return sprintf(
            'pph-final-%s-%04d-%02d.%s',
            str($report['entity_label'])->slug(),
            $report['year'],
            $report['month'],
            $extension,
        );
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     rate: float,
     *     gross_cash_in: float,
     *     net_omzet: float,
     *     pph_final: float,
     *     computed_pph: float,
     *     tax_paid: float,
     *     rows: Collection<int, array<string, mixed>>,
     * }
     */
    private function emptyReport(int $year, int $month, int $entityId, bool $isConsolidated, float $rate): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'entity_id' => $entityId,
            'entity_label' => $this->entityLabel($entityId),
            'is_consolidated' => $isConsolidated,
            'rate' => $rate,
            'gross_cash_in' => 0.0,
            'net_omzet' => 0.0,
            'pph_final' => 0.0,
            'computed_pph' => 0.0,
            'tax_paid' => 0.0,
            'rows' => collect(),
        ];
    }
}
