<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\AgingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayablesReportController extends Controller
{
    public function __invoke(Request $request, AgingReportService $aging)
    {
        Gate::authorize(Report::getPermissions()['view-payables']);

        $year = max(
            AgingReportService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $entityId = $this->resolveEntityId($request->query('entity'));
        $refresh = $request->query('refresh') === '1';

        $isConsolidated = $entityId === null || $entityId === AgingReportService::CONSOLIDATED_ENTITY;
        $report = $aging->build(AgingReportService::KIND_PAYABLE, $year, $month, $entityId, $refresh);

        if ($request->query('export') === 'csv') {
            return $aging->exportCsv($report);
        }

        if ($request->query('export') === 'xlsx') {
            return $aging->exportXlsx($report);
        }

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        return view('reports.payables', [
            'entities' => $entities,
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? AgingReportService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $aging->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === AgingReportService::CONSOLIDATED_ENTITY) {
            return AgingReportService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
