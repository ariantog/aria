<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Produksi\ProduksiStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProduksiPritilReportController extends Controller
{
    public function __invoke(Request $request, ProduksiStatisticsService $stats)
    {
        Gate::authorize(Report::getPermissions()['view-produksi-pritil']);

        $month = $request->query('month');
        $month = ($month === null || $month === '' || $month === '0') ? null : (int) $month;
        $year = (int) ($request->query('year') ?? date('Y'));

        [$startDate, $endDate, $resolvedMonth, $resolvedYear] = $stats->resolveDateRange($month, $year);

        $workerSummary = $stats->pritilWorkerSummary($startDate, $endDate);
        $monthlyTotals = $stats->pritilMonthlyTotals($resolvedYear);

        return view('reports.produksi-pritil', [
            'workerSummary' => $workerSummary,
            'monthlyTotals' => $monthlyTotals,
            'filters' => [
                'month' => $resolvedMonth,
                'year' => $resolvedYear,
            ],
            'yearList' => $stats->yearList(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
