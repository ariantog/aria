<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\NettCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NettCashController extends Controller
{
    public function __invoke(Request $request, NettCashService $nettCash)
    {
        Gate::authorize(Report::getPermissions()['view-nett-cash']);

        $year = max(
            NettCashService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = $this->resolveMonth($request->query('month', now()->month));
        $entityId = $this->resolveEntityId($request->query('entity'));
        $isConsolidated = $entityId === NettCashService::CONSOLIDATED_ENTITY;

        $report = $nettCash->build($year, $month, $entityId);

        if ($request->query('export') === 'csv') {
            return $nettCash->exportCsv($report);
        }

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        return view('reports.nett-cash-sby', [
            'entities' => $entities,
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? NettCashService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $nettCash->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveMonth(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'all' || $raw === '0' || $raw === 0) {
            return null;
        }

        return max(1, min(12, (int) $raw));
    }

    private function resolveEntityId(mixed $raw): int
    {
        if ($raw === null || $raw === '' || (int) $raw === NettCashService::CONSOLIDATED_ENTITY) {
            return NettCashService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
