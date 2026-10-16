<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Transactions\PrepareMoveTransactionDraft;
use App\Http\Controllers\Controller;
use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Report;
use App\Services\WarehouseArrangementExportService;
use App\Services\WarehouseArrangementRefreshService;
use App\Services\WarehouseArrangementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class WarehouseArrangementController extends Controller
{
    private const SESSION_DRAFTED_KEY = 'warehouse_arrangement_drafted';

    public function index(
        Request $request,
        WarehouseArrangementService $arrangementService,
        WarehouseArrangementRefreshService $refreshService,
    )
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $destinations = $arrangementService->destinationWarehouses();
        $demandDays = (int) $request->query('demand_days', 365);
        if (! in_array($demandDays, [30, 90, 180, 365], true)) {
            $demandDays = 365;
        }

        $mode = (string) $request->query('mode', WarehouseArrangementService::MODE_DEMAND);
        if (! in_array($mode, WarehouseArrangementService::validModes(), true)) {
            $mode = WarehouseArrangementService::MODE_DEMAND;
        }

        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $sourceWarehouse1Id = (int) $request->query('source_wh1_id', 0);
        $sourceWarehouse1Id = $sourceWarehouse1Id > 0 ? $sourceWarehouse1Id : null;
        $sourceWarehouse2Id = (int) $request->query('source_wh2_id', 0);
        $sourceWarehouse2Id = $sourceWarehouse2Id > 0 ? $sourceWarehouse2Id : null;

        $warehouseId = (int) $request->query('warehouse_id');
        if (! $warehouseId && $destinations->isNotEmpty()) {
            $warehouseId = $destinations->first()->id;
        }

        $excludeItemIds = $this->draftedItemIdsForWarehouse($warehouseId);

        $result = null;
        $sections = [];
        $cacheDiagnostics = null;

        if ($warehouseId && $destinations->contains('id', $warehouseId)) {
            $result = $arrangementService->buildPage(
                $warehouseId,
                $demandDays,
                $mode,
                $page,
                WarehouseArrangementService::PER_PAGE,
                $search,
                $excludeItemIds,
                $sourceWarehouse1Id,
                $sourceWarehouse2Id,
            );
            $sections = $result['sections'];
            $cacheDiagnostics = $arrangementService->cacheDiagnostics($warehouseId);
        }

        $totalPcodes = $result['total_pcodes'] ?? 0;
        $perPage = $result['per_page'] ?? WarehouseArrangementService::PER_PAGE;
        $lastPage = $totalPcodes > 0 ? (int) ceil($totalPcodes / $perPage) : 1;

        $activeRefreshJob = $warehouseId
            ? $refreshService->activeJobForWarehouse($warehouseId)
            : null;
        $lastRefreshJob = $warehouseId
            ? $refreshService->lastFinishedJobForWarehouse($warehouseId)
            : null;

        return view('reports.warehouse-arrangement', [
            'destinations' => $destinations,
            'selectedWarehouseId' => $warehouseId,
            'demandDays' => $demandDays,
            'mode' => $result['mode'] ?? $mode,
            'page' => $result['page'] ?? $page,
            'perPage' => $perPage,
            'totalPcodes' => $totalPcodes,
            'lastPage' => $lastPage,
            'search' => $result['search'] ?? $search,
            'sections' => $sections,
            'sourceWarehouses' => $result['source_warehouses'] ?? [],
            'sourceWarehouse1' => $result['source_warehouse_1'] ?? null,
            'sourceWarehouse2' => $result['source_warehouse_2'] ?? null,
            'selectedSourceWarehouse1Id' => ($result['source_warehouse_1']['id'] ?? null),
            'selectedSourceWarehouse2Id' => ($result['source_warehouse_2']['id'] ?? null),
            'topSourceMatches' => $result['top_source_matches'] ?? [],
            'sourceMatchRankings' => $result['source_match_rankings'] ?? [],
            'destinationName' => $result['destination']->name ?? null,
            'syncedAt' => $result['synced_at'] ?? null,
            'stale' => $result['stale'] ?? false,
            'cacheDiagnostics' => $cacheDiagnostics,
            'activeRefreshJob' => $activeRefreshJob,
            'lastRefreshJob' => $lastRefreshJob,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function refresh(Request $request, WarehouseArrangementRefreshService $refreshService)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'demand_days' => ['nullable', 'integer', 'in:30,90,180,365'],
            'mode' => ['nullable', 'string', 'in:'.WarehouseArrangementService::MODE_DEMAND.','.WarehouseArrangementService::MODE_FAMILY],
            'source_wh1_id' => ['nullable', 'integer', 'exists:customers,id'],
            'source_wh2_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];

        Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($warehouseId);

        if ($refreshService->activeJobForWarehouse($warehouseId)) {
            return redirect()
                ->route('reports.warehouse-arrangement', $this->refreshRedirectParams($validated))
                ->with('error', 'A rebuild is already queued or running for this warehouse.');
        }

        try {
            $refreshService->createJob($warehouseId, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('reports.warehouse-arrangement', $this->refreshRedirectParams($validated))
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reports.warehouse-arrangement', $this->refreshRedirectParams($validated))
            ->with('success', 'Rebuild queued. Stats and arrangement cache will refresh in the background (about 300 SKUs per minute).');
    }

    public function cancelRefresh(Request $request, WarehouseArrangementRefreshService $refreshService)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'demand_days' => ['nullable', 'integer', 'in:30,90,180,365'],
            'mode' => ['nullable', 'string', 'in:'.WarehouseArrangementService::MODE_DEMAND.','.WarehouseArrangementService::MODE_FAMILY],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $job = $refreshService->activeJobForWarehouse($warehouseId);

        if (! $job) {
            return redirect()
                ->route('reports.warehouse-arrangement', $this->refreshRedirectParams($validated))
                ->with('error', 'No active rebuild job for this warehouse.');
        }

        $refreshService->failJob($job, 'Cancelled by '.$request->user()?->name.'.');

        return redirect()
            ->route('reports.warehouse-arrangement', $this->refreshRedirectParams($validated))
            ->with('success', 'Rebuild cancelled. You can start a new one.');
    }

    public function tickRefresh(Request $request, WarehouseArrangementRefreshService $refreshService)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];

        Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($warehouseId);

        $job = $refreshService->activeJobForWarehouse($warehouseId);
        if (! $job) {
            $lastJob = $refreshService->lastFinishedJobForWarehouse($warehouseId);

            return response()->json($lastJob
                ? array_merge($refreshService->jobProgressPayload($lastJob), ['active' => false, 'done' => true])
                : ['active' => false, 'done' => true]);
        }

        $lock = Cache::lock('warehouse-arrangement-refresh:'.$warehouseId, 120);

        if (! $lock->get()) {
            return response()->json(array_merge(
                $refreshService->jobProgressPayload($job),
                ['busy' => true],
            ));
        }

        try {
            $refreshService->processUntilDeadline($job, 25);
            $job->refresh();

            return response()->json($refreshService->jobProgressPayload($job));
        } catch (\Throwable $e) {
            $refreshService->failJob($job, $e->getMessage());

            return response()->json(array_merge(
                $refreshService->jobProgressPayload($job->fresh()),
                ['failed' => true],
            ), 500);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function refreshRedirectParams(array $validated): array
    {
        return array_filter([
            'warehouse_id' => (int) $validated['warehouse_id'],
            'demand_days' => isset($validated['demand_days']) ? (int) $validated['demand_days'] : null,
            'mode' => $validated['mode'] ?? null,
            'source_wh1_id' => isset($validated['source_wh1_id']) ? (int) $validated['source_wh1_id'] : null,
            'source_wh2_id' => isset($validated['source_wh2_id']) ? (int) $validated['source_wh2_id'] : null,
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

        $mode = (string) $request->query('mode', WarehouseArrangementService::MODE_DEMAND);
        if (! in_array($mode, WarehouseArrangementService::validModes(), true)) {
            $mode = WarehouseArrangementService::MODE_DEMAND;
        }

        abort_unless($warehouseId > 0, 404);

        $result = $arrangementService->buildSuggestionsForExport(
            $warehouseId,
            $demandDays,
            $mode,
            $this->draftedItemIdsForWarehouse($warehouseId),
        );

        return $exportService->download(
            $result['suggestions'],
            $result['destination']->name,
            $demandDays,
            $mode,
        );
    }

    public function draftMove(Request $request, PrepareMoveTransactionDraft $prepareMove)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.from_warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'items.*.to_warehouse_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $items = collect($validated['items'])->map(fn (array $row) => [
            'item_id' => (int) $row['item_id'],
            'quantity' => (float) $row['quantity'],
            'from_warehouse_id' => (int) $row['from_warehouse_id'],
            'to_warehouse_id' => (int) $row['to_warehouse_id'],
        ])->all();

        return $prepareMove->execute($items, rememberArrangementDraft: true);
    }

    /**
     * @return list<int>
     */
    private function draftedItemIdsForWarehouse(int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return [];
        }

        $all = session(self::SESSION_DRAFTED_KEY, []);

        return array_values(array_map('intval', $all[$warehouseId] ?? []));
    }

}
