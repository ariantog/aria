<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Services\Restock\RestockRecommendationApplyService;
use App\Services\Restock\RestockRecommendationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RestockRecommendationController extends Controller
{
    public function index(Request $request, RestockRecommendationService $service): View
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        $payload = $service->build($request, $request->user());

        $itemType = $payload['item_type'];
        $itemTypeQuery = $itemType !== null ? (string) $itemType->value : '';
        $salesWindow = $payload['sales_window'];
        $salesWindowQuery = $salesWindow === RestockRecommendationService::SALES_WINDOW_YEAR
            ? (string) RestockRecommendationService::SALES_WINDOW_YEAR
            : '';

        $healthWarehouseId = $service->resolveHealthWarehouseId($request);

        return view('restock.recommendations', [
            'tab' => $payload['tab'],
            'salesWindow' => $salesWindow,
            'salesWindowQuery' => $salesWindowQuery,
            'salesWindowOptions' => RestockRecommendationService::salesWindowFilterOptions(),
            'itemType' => $itemType,
            'itemTypeQuery' => $itemTypeQuery,
            'itemTypeOptions' => RestockRecommendationService::itemTypeFilterOptions(),
            'healthWindows' => $payload['health_windows'],
            'healthSource' => $payload['health_source'],
            'insightPeriod' => $payload['insight_period'],
            'insightCalculated' => $payload['insight_calculated'],
            'fastMoving' => $payload['fast_moving'],
            'highMargin' => $payload['high_margin'],
            'defaultHealthWindow' => $service->defaultHealthWindowLabels(),
            'healthWarehouseId' => $healthWarehouseId,
            'inventoryHealthUrl' => route('reports.inventory-health', array_filter([
                'status' => \App\Services\InventoryHealth\InventoryHealthClassifier::LOW,
                'warehouse_id' => $healthWarehouseId,
            ])),
            'itemInsightsUrl' => $payload['insight_period']
                ? route('reports.item-insights', [
                    'period' => $payload['insight_period']->periodLabel(),
                    'tab' => \App\Models\ItemInsightRanking::CATEGORY_MOST_PROFITABLE,
                ])
                : route('reports.item-insights'),
            'canApplyToSheets' => $request->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
        ]);
    }

    public function applyToSheets(
        Request $request,
        RestockRecommendationService $recommendations,
        RestockRecommendationApplyService $apply,
    ): RedirectResponse {
        Gate::authorize(RestockSheet::getPermissions()['edit']);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['integer', 'min:1'],
        ]);

        $itemIds = array_values(array_unique(array_map('intval', $validated['item_ids'])));
        $rates = collect($recommendations->monthlySellingRatesForItems($request, $request->user(), $itemIds));
        $result = $apply->applyOneMonthRate($itemIds, $rates, $request->user());

        $redirect = redirect()->route('restock.recommendations', array_filter([
            'tab' => $request->input('tab', $request->query('tab')),
            'item_type' => $request->input('item_type', $request->query('item_type')),
            'sales_window' => $request->input('sales_window', $request->query('sales_window')),
            'from' => $request->input('from', $request->query('from')),
            'to' => $request->input('to', $request->query('to')),
            'page' => $request->input('page', $request->query('page')),
        ], fn ($value) => $value !== null && $value !== ''));

        $appliedCount = count($result['applied']);
        $skippedCount = count($result['skipped']);

        if ($appliedCount > 0) {
            $redirect->with(
                'success',
                $appliedCount === 1
                    ? sprintf(
                        'Set restock qty to at least 1 month sell rate (%d units) on %s.',
                        $result['applied'][0]['qty'],
                        $result['applied'][0]['sheet_name'],
                    )
                    : sprintf('Updated restock qty for %d SKUs (1 month sell rate).', $appliedCount),
            );
        }

        if ($skippedCount > 0) {
            $redirect->with('apply_skipped', $result['skipped']);
            if ($appliedCount === 0) {
                $redirect->with('error', 'No SKUs were added to restock sheets.');
            }
        }

        return $redirect;
    }
}
