<?php

namespace App\Http\Controllers;

use App\Services\Jubelio\JubelioLegacyCatchupStockRestoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JubelioLegacyCatchupRepairController extends Controller
{
    public function index(JubelioLegacyCatchupStockRestoreService $service): View
    {
        $warehouseId = request()->query('warehouse_id');
        $warehouseId = $warehouseId !== null && $warehouseId !== '' ? (int) $warehouseId : null;

        $sells = $service->paginateProblematicSells($warehouseId);
        $restoredMoveIds = $service->restoredMoveLinksForPage($sells->getCollection());

        return view('system-settings.jubelio-legacy-catchup-repair', [
            'sells' => $sells,
            'warehouses' => $service->warehousesOnProblematicSells(),
            'virtualWarehouses' => $service->virtualWarehouses(),
            'warehouseFilter' => $warehouseId,
            'restoredMoveIds' => $restoredMoveIds,
            'criteria' => [
                'date_before' => $service->transactionDateBefore(),
                'created_after' => $service->createdAfter(),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'restore_report' => session('restore_report'),
            ],
        ]);
    }

    public function restore(Request $request, JubelioLegacyCatchupStockRestoreService $service): RedirectResponse
    {
        $validated = $request->validate([
            'virtual_warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],
            'confirm' => ['accepted'],
        ]);

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        try {
            $report = $service->restoreStockForSells(
                $validated['transaction_ids'],
                (int) $validated['virtual_warehouse_id'],
                (int) $user->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $restoredCount = count($report['restored']);
        $skippedCount = count($report['skipped']);
        $errorCount = count($report['errors']);

        $message = sprintf(
            'Selesai: %d move dibuat, %d dilewati, %d error.',
            $restoredCount,
            $skippedCount,
            $errorCount,
        );

        $warehouseId = $request->input('warehouse_id');

        return redirect()
            ->route('jubelio-legacy-catchup-repair.index', array_filter([
                'warehouse_id' => $warehouseId !== null && $warehouseId !== '' ? (int) $warehouseId : null,
            ]))
            ->with('success', $message)
            ->with('restore_report', $report);
    }
}
