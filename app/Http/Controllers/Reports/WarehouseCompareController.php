<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\WarehouseCompare\WarehouseCompareExportService;
use App\Services\WarehouseCompare\WarehouseCompareService;
use App\Services\WarehouseComparePreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseCompareController extends Controller
{
    public function index(
        Request $request,
        WarehouseCompareService $compareService,
        WarehouseComparePreferenceService $preferences,
    ): View {
        Gate::authorize(Report::getPermissions()['view-warehouse-compare']);

        $page = $compareService->buildPage($request, $request->user());

        return view('reports.warehouse-compare', [
            'warehouses' => $page['warehouses'],
            'selectedWarehouseIds' => $page['selected_warehouse_ids'],
            'pivotWarehouse' => $page['pivot_warehouse'],
            'itemType' => $page['item_type'],
            'sort' => $page['sort'],
            'grid' => $page['grid'],
            'sortOptions' => WarehouseCompareService::sortLabels(),
            'itemTypeOptions' => WarehouseCompareService::itemTypeLabels(),
            'maxWarehouses' => \App\Support\UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES,
        ]);
    }

    public function export(
        Request $request,
        WarehouseCompareService $compareService,
        WarehouseCompareExportService $exportService,
    ): StreamedResponse {
        Gate::authorize(Report::getPermissions()['view-warehouse-compare']);

        $page = $compareService->buildPage($request, $request->user());
        $pivotName = $page['pivot_warehouse']?->name ?? 'warehouse-compare';

        return $exportService->download($page['grid'], 'warehouse-compare-'.$pivotName);
    }

    public function saveDisplay(Request $request, WarehouseComparePreferenceService $preferences): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-compare']);

        $warehouseIds = $preferences->normalizeWarehouseIds(
            $request->input('warehouse_ids', []),
            $request->user(),
            strict: true,
        );

        $validated = $request->validate([
            'item_type' => ['required'],
            'sort' => ['required', 'string'],
        ]);

        if ($warehouseIds === []) {
            return back()
                ->withErrors(['warehouse_ids' => 'Select at least one warehouse (pivot).'])
                ->withInput();
        }

        $payload = [
            'warehouse_ids' => $warehouseIds,
            'item_type' => $validated['item_type'],
            'sort' => $validated['sort'],
        ];

        try {
            $preferences->save($request->user(), $payload);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reports.warehouse-compare', [
                'warehouse_ids' => $warehouseIds,
                'item_type' => $payload['item_type'],
                'sort' => $payload['sort'],
            ])
            ->with('success', 'Display settings saved.');
    }
}
