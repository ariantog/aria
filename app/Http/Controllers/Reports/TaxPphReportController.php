<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Reporting\PphFinalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaxPphReportController extends Controller
{
    public function __invoke(Request $request, PphFinalReportService $pphFinal)
    {
        Gate::authorize(Report::getPermissions()['view-tax-pph']);

        $year = max(
            PphFinalReportService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $entityId = $this->resolveEntityId($request->query('entity'));

        $isConsolidated = $entityId === null || $entityId === PphFinalReportService::CONSOLIDATED_ENTITY;
        $report = $pphFinal->build($year, $month, $entityId);

        if ($request->query('export') === 'csv') {
            return $pphFinal->exportCsv($report);
        }

        if ($request->query('export') === 'xlsx') {
            return $pphFinal->exportXlsx($report);
        }

        return view('reports.tax.pph', [
            'entities' => $pphFinal->nonPkpEntities(),
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? PphFinalReportService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $pphFinal->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === PphFinalReportService::CONSOLIDATED_ENTITY) {
            return PphFinalReportService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
