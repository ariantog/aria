<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\TaxReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaxPpnReportController extends Controller
{
    public function __invoke(Request $request, TaxReportService $taxReport)
    {
        Gate::authorize(Report::getPermissions()['view-tax-ppn']);

        $year = max(
            TaxReportService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $entityId = $this->resolveEntityId($request->query('entity'));

        $entityLabel = $taxReport->entityLabel($entityId);

        if ($request->query('export') === 'csv') {
            return $taxReport->exportCsv($year, $month, $entityId, $entityLabel);
        }

        if ($request->query('export') === 'xlsx') {
            return $taxReport->exportXlsx($year, $month, $entityId, $entityLabel);
        }

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        $isConsolidated = $entityId === null || $entityId === TaxReportService::CONSOLIDATED_ENTITY;

        return view('reports.tax.ppn', [
            'entities' => $entities,
            'ringkasan' => $taxReport->ringkasan($year, $month, $entityId),
            'keluaranRows' => $taxReport->keluaranRows($year, $month, $entityId),
            'masukanRows' => $taxReport->masukanRows($year, $month, $entityId),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? TaxReportService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $taxReport->yearOptions(),
            'entityLabel' => $taxReport->entityLabel($entityId),
            'showEntityColumn' => $isConsolidated,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

  private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === TaxReportService::CONSOLIDATED_ENTITY) {
            return TaxReportService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
