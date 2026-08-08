<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\WarehouseArrangementExportService;
use App\Services\WarehouseArrangementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarehouseArrangementController extends Controller
{
    public function index(Request $request, WarehouseArrangementService $arrangementService)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $destinations = $arrangementService->destinationWarehouses();
        $demandDays = (int) $request->query('demand_days', 365);
        if (! in_array($demandDays, [30, 90, 180, 365], true)) {
            $demandDays = 365;
        }

        $warehouseId = (int) $request->query('warehouse_id');
        if (! $warehouseId && $destinations->isNotEmpty()) {
            $warehouseId = $destinations->first()->id;
        }

        $result = null;
        $truncated = false;
        $totalSuggestionCount = 0;

        if ($warehouseId && $destinations->contains('id', $warehouseId)) {
            $result = $arrangementService->buildSuggestions($warehouseId, $demandDays);
            $truncated = $result['truncated'];
            $totalSuggestionCount = $result['total_suggestion_count'];
        }

        return view('reports.warehouse-arrangement', [
            'destinations' => $destinations,
            'selectedWarehouseId' => $warehouseId,
            'demandDays' => $demandDays,
            'families' => $result['families'] ?? [],
            'suggestions' => $result['suggestions'] ?? [],
            'destinationName' => $result['destination']->name ?? null,
            'truncated' => $truncated,
            'totalSuggestionCount' => $totalSuggestionCount,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function export(Request $request, WarehouseArrangementService $arrangementService, WarehouseArrangementExportService $exportService)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $warehouseId = (int) $request->query('warehouse_id');
        $demandDays = (int) $request->query('demand_days', 365);
        if (! in_array($demandDays, [30, 90, 180, 365], true)) {
            $demandDays = 365;
        }

        abort_unless($warehouseId > 0, 404);

        $result = $arrangementService->buildSuggestions(
            $warehouseId,
            $demandDays,
            WarehouseArrangementService::EXPORT_MAX_FAMILIES,
            WarehouseArrangementService::EXPORT_MAX_SUGGESTIONS,
        );

        return $exportService->download(
            $result['suggestions'],
            $result['destination']->name,
            $demandDays,
        );
    }
}
