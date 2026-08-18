<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Services\WarehouseArrangementExportService;
use App\Services\WarehouseArrangementRefreshService;
use App\Services\WarehouseArrangementService;
use Illuminate\Http\Request;
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

    public function draftMove(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.from_warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'items.*.to_warehouse_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $fromId = (int) $validated['items'][0]['from_warehouse_id'];
        $toId = (int) $validated['items'][0]['to_warehouse_id'];

        foreach ($validated['items'] as $row) {
            if ((int) $row['from_warehouse_id'] !== $fromId || (int) $row['to_warehouse_id'] !== $toId) {
                return back()->with('error', 'All items in one draft must share the same source and destination warehouses.');
            }
        }

        $draftItemIds = collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id);
        if ($draftItemIds->unique()->count() !== $draftItemIds->count()) {
            return back()->with('error', 'Each SKU can only be selected once.');
        }

        $from = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->findOrFail($fromId);

        $to = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($toId);

        $itemIds = $draftItemIds->all();
        $items = Item::query()
            ->with('warehouseItems')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $prefillItems = [];
        foreach ($validated['items'] as $row) {
            $item = $items->get((int) $row['item_id']);
            if (! $item) {
                continue;
            }

            $quantity = (float) $row['quantity'];
            $price = (float) $item->price;

            $prefillItems[] = [
                'item_id' => (string) $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => 0,
                'subtotal' => $quantity * $price,
                'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ];
        }

        if ($prefillItems === []) {
            return back()->with('error', 'No valid items selected for the move draft.');
        }

        $this->rememberDraftedItems($toId, $itemIds);

        session([
            'transaction_move_prefill' => [
                'sender_id' => (string) $from->id,
                'sender' => ['id' => $from->id, 'name' => $from->name],
                'receiver_id' => (string) $to->id,
                'receiver' => ['id' => $to->id, 'name' => $to->name],
                'items' => $prefillItems,
            ],
        ]);

        return redirect()->route('transactions.create', ['type' => 'move']);
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

    /**
     * @param  list<int>  $itemIds
     */
    private function rememberDraftedItems(int $destinationWarehouseId, array $itemIds): void
    {
        $all = session(self::SESSION_DRAFTED_KEY, []);
        $existing = array_map('intval', $all[$destinationWarehouseId] ?? []);
        $merged = array_values(array_unique(array_merge($existing, array_map('intval', $itemIds))));

        $all[$destinationWarehouseId] = $merged;
        session([self::SESSION_DRAFTED_KEY => $all]);
    }
}
