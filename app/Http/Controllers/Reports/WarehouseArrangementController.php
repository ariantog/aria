<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Item;
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
            'sourceSlotCount' => $result
                ? min(
                    WarehouseArrangementService::MAX_SOURCE_SLOTS,
                    (int) collect($result['suggestions'])->max(fn (array $s) => count($s['sources'] ?? []))
                )
                : 0,
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

    public function draftMove(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.from_warehouse_id' => ['required', 'integer', 'exists:addrbooks,id'],
            'items.*.to_warehouse_id' => ['required', 'integer', 'exists:addrbooks,id'],
        ]);

        $fromId = (int) $validated['items'][0]['from_warehouse_id'];
        $toId = (int) $validated['items'][0]['to_warehouse_id'];

        foreach ($validated['items'] as $row) {
            if ((int) $row['from_warehouse_id'] !== $fromId || (int) $row['to_warehouse_id'] !== $toId) {
                return back()->with('error', 'Selected items must share the same source and destination warehouses.');
            }
        }

        $draftItemIds = collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id);
        if ($draftItemIds->unique()->count() !== $draftItemIds->count()) {
            return back()->with('error', 'Each SKU can only be selected once. Pick one source warehouse per item.');
        }

        $from = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->findOrFail($fromId);

        $to = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($toId);

        $itemIds = collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id)->all();
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
                'warehouse_items' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ];
        }

        if ($prefillItems === []) {
            return back()->with('error', 'No valid items selected for the move draft.');
        }

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
}
