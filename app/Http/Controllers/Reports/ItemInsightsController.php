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

        $view = $query->resolveView(
            $request->query('period'),
            $request->query('grain'),
        );
        $category = $query->normalizeCategory($request->query('tab'));
        $year = $view['year'];
        $month = $view['month'];
        $grain = $view['grain'];
        $periodKey = $view['period_key'];

        $calculated = $query->isCalculated($year, $month);
        $meta = $query->periodMeta($year, $month);

        return view('reports.item-insights', [
            'category' => $category,
            'categoryLabels' => \App\Models\ItemInsightRanking::categoryLabels(),
            'grain' => $grain,
            'year' => $year,
            'month' => $month,
            'periodKey' => $periodKey,
            'calculated' => $calculated,
            'periodMeta' => $meta,
            'calculatedMonths' => $query->calculatedMonths(),
            'calculatedYears' => $query->calculatedYears(),
            'rows' => $calculated ? $query->rankingsFor($year, $month, $category) : collect(),
        ]);
    }

    public function recalculate(Request $request, ItemInsightSyncService $sync): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-item-insights']);

        $validated = $request->validate([
            'grain' => ['required', 'in:month,year'],
            'period' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'tab' => ['nullable', 'string'],
        ]);

        $tab = $request->input('tab');
        $grain = $validated['grain'];

        if ($grain === ItemInsightQueryService::GRAIN_YEAR) {
            if (! preg_match('/^(\d{4})$/', $validated['period'], $matches)) {
                return redirect()->back()->withErrors(['period' => 'Use a four-digit year for yearly recalculate.']);
            }

            $year = (int) $matches[1];
            $result = $sync->recalculateYear($year, Auth::id());
            $periodKey = sprintf('%04d', $year);
            $monthsNote = isset($result['months_included']) && $result['months_included'] !== []
                ? ' (months included: '.implode(', ', array_map(fn (int $m) => sprintf('%02d', $m), $result['months_included'])).')'
                : '';

            $message = sprintf(
                'Recalculated year %s%s — %d ranking rows stored at %s.',
                $periodKey,
                $monthsNote,
                $result['rows'],
                $result['calculated_at'],
            );

            return redirect()
                ->route('reports.item-insights', array_filter([
                    'grain' => 'year',
                    'period' => $periodKey,
                    'tab' => is_string($tab) ? $tab : null,
                ]))
                ->with('status', $message);
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', $validated['period'], $matches)) {
            return redirect()->back()->withErrors(['period' => 'Use YYYY-MM for monthly recalculate.']);
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        if ($month < 1 || $month > 12) {
            return redirect()->back()->withErrors(['period' => 'Invalid month.']);
        }

        $result = $sync->recalculateMonth($year, $month, Auth::id());
        $periodKey = sprintf('%04d-%02d', $year, $month);

        return redirect()
            ->route('reports.item-insights', array_filter([
                'grain' => 'month',
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
