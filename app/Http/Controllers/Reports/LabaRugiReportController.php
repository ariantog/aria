<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\LabaRugiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LabaRugiReportController extends Controller
{
    public function __invoke(Request $request, LabaRugiService $labaRugi)
    {
        Gate::authorize(Report::getPermissions()['view-laba-rugi']);

        $year = max(
            LabaRugiService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $months = $labaRugi->normalizePeriod((int) $request->query('months', 1));
        $entityId = $this->resolveEntityId($request->query('entity'));

        $isConsolidated = $entityId === null || $entityId === LabaRugiService::CONSOLIDATED_ENTITY;
        $report = $labaRugi->build($year, $month, $months, $entityId);

        if ($request->query('export') === 'csv') {
            return $labaRugi->exportCsv($report);
        }

        if ($request->query('export') === 'xlsx') {
            return $labaRugi->exportXlsx($report);
        }

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        return view('reports.laba-rugi', [
            'entities' => $entities,
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'months' => $months,
                'entity' => $isConsolidated ? LabaRugiService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $labaRugi->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === LabaRugiService::CONSOLIDATED_ENTITY) {
            return LabaRugiService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
