<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Produksi\ProduksiStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProduksiJahitReportController extends Controller
{
    public function __invoke(Request $request, ProduksiStatisticsService $stats)
    {
        Gate::authorize(Report::getPermissions()['view-produksi-jahit']);

        $ctx = $stats->reportContext($request->query());

        return view('reports.produksi-jahit', [
            'workerSummary' => $stats->jahitWorkerSummary($ctx['startDate'], $ctx['endDate'], $ctx['status']),
            'monthlyTotals' => $stats->jahitMonthlyTotals($ctx['filters']['year'], $ctx['status']),
            'filters' => $ctx['filters'],
            'yearList' => $stats->yearList(),
            'periodLabel' => $ctx['periodLabel'],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
