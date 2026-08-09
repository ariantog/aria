<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ProductPerformanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductPerformanceController extends Controller
{
    public function index(Request $request, ProductPerformanceService $performance): View
    {
        Gate::authorize(Report::getPermissions()['view-product-performance']);

        $tab = (string) $request->query('tab', ProductPerformanceService::TAB_SALES);
        if (! in_array($tab, ProductPerformanceService::validTabs(), true)) {
            $tab = ProductPerformanceService::TAB_SALES;
        }

        $periodDays = (int) $request->query('period', 90);
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $itemType = $performance->normalizeItemType($request->query('item_type'));
        $grain = (string) $request->query('grain', 'type_size');
        if (! array_key_exists($grain, ProductPerformanceService::attributeGrainOptions())) {
            $grain = 'type_size';
        }

        $result = $performance->fetch($tab, $periodDays, $warehouseId, $itemType, $grain);

        return view('reports.product-performance', [
            'tab' => $tab,
            'periodDays' => in_array($periodDays, \App\Services\Items\ItemDimensionResolver::validPeriods(), true) ? $periodDays : 90,
            'warehouseId' => $warehouseId,
            'itemType' => $request->query('item_type', 'all'),
            'grain' => $grain,
            'rows' => $result['rows'],
            'syncedAt' => $result['synced_at'],
            'stale' => $result['stale'],
            'hasData' => $performance->hasData(),
            'warehouses' => $performance->warehouses(),
            'grainOptions' => ProductPerformanceService::attributeGrainOptions(),
        ]);
    }
}
