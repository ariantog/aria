<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\NeracaService;
use App\Services\Reporting\TaxReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NeracaReportController extends Controller
{
    public function __invoke(Request $request, NeracaService $neraca)
    {
        Gate::authorize(Report::getPermissions()['view-neraca']);

        $year = max(
            NeracaService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $entityId = $this->resolveEntityId($request->query('entity'));
        $refresh = $request->query('refresh') === '1';

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        $isConsolidated = $entityId === null || $entityId === NeracaService::CONSOLIDATED_ENTITY;
        $report = $neraca->build($year, $month, $entityId, $refresh);

        return view('reports.neraca', [
            'entities' => $entities,
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? NeracaService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $neraca->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === TaxReportService::CONSOLIDATED_ENTITY) {
            return NeracaService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
