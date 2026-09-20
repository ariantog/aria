<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Services\Restock\RestockRecommendationService;
use Illuminate\Contracts\View\View;
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

        return view('restock.recommendations', [
            'tab' => $payload['tab'],
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
            'inventoryHealthUrl' => route('reports.inventory-health', [
                'status' => \App\Services\InventoryHealth\InventoryHealthClassifier::LOW,
            ]),
            'itemInsightsUrl' => $payload['insight_period']
                ? route('reports.item-insights', [
                    'period' => $payload['insight_period']->periodLabel(),
                    'tab' => \App\Models\ItemInsightRanking::CATEGORY_MOST_PROFITABLE,
                ])
                : route('reports.item-insights'),
        ]);
    }
}
