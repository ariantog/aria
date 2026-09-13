<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ItemInsightQueryService;
use App\Services\ItemInsightSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ItemInsightsController extends Controller
{
    public function index(Request $request, ItemInsightQueryService $query): View
    {
        Gate::authorize(Report::getPermissions()['view-item-insights']);

        $period = $query->resolvePeriod($request->query('period'));
        $category = $query->normalizeCategory($request->query('tab'));
        $year = $period['year'];
        $month = $period['month'];
        $periodKey = sprintf('%04d-%02d', $year, $month);

        $calculated = $query->isCalculated($year, $month);
        $meta = $query->monthMeta($year, $month);

        return view('reports.item-insights', [
            'category' => $category,
            'categoryLabels' => \App\Models\ItemInsightRanking::categoryLabels(),
            'year' => $year,
            'month' => $month,
            'periodKey' => $periodKey,
            'calculated' => $calculated,
            'monthMeta' => $meta,
            'calculatedMonths' => $query->calculatedMonths(),
            'rows' => $calculated ? $query->rankingsFor($year, $month, $category) : collect(),
        ]);
    }

    public function recalculate(Request $request, ItemInsightSyncService $sync): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-item-insights']);

        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'tab' => ['nullable', 'string'],
        ]);

        $year = (int) substr($validated['period'], 0, 4);
        $month = (int) substr($validated['period'], 5, 2);
        if ($month < 1 || $month > 12) {
            return redirect()
                ->back()
                ->withErrors(['period' => 'Invalid month.']);
        }

        $result = $sync->recalculateMonth($year, $month, Auth::id());

        $periodKey = sprintf('%04d-%02d', $year, $month);
        $tab = $request->input('tab');

        return redirect()
            ->route('reports.item-insights', array_filter([
                'period' => $periodKey,
                'tab' => is_string($tab) ? $tab : null,
            ]))
            ->with('status', sprintf(
                'Recalculated %s — %d ranking rows stored at %s.',
                $periodKey,
                $result['rows'],
                $result['calculated_at'],
            ));
    }
}
