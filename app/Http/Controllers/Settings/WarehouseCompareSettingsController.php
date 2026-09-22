<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\WarehouseCompare\WarehouseCompareService;
use App\Services\WarehouseComparePreferenceService;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class WarehouseCompareSettingsController extends Controller
{
    public function edit(Request $request, WarehouseComparePreferenceService $preferences): View
    {
        $user = $request->user();
        $saved = $preferences->defaults($user);
        $warehouses = $preferences->selectableWarehouses($user);

        $slots = [];
        for ($i = 0; $i < UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES; $i++) {
            $slots[$i] = $saved['warehouse_ids'][$i] ?? null;
        }

        return view('settings.warehouse-compare', [
            'warehouses' => $warehouses,
            'slots' => $slots,
            'itemType' => $saved['item_type'],
            'sort' => $saved['sort'],
            'maxWarehouses' => UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES,
            'sortOptions' => WarehouseCompareService::sortLabels(),
            'itemTypeOptions' => WarehouseCompareService::itemTypeLabels(),
        ]);
    }

    public function update(Request $request, WarehouseComparePreferenceService $preferences): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_ids' => ['nullable', 'array', 'max:'.UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES],
            'warehouse_ids.*' => ['nullable', 'integer', 'exists:customers,id'],
            'item_type' => ['required'],
            'sort' => ['required', 'string'],
        ]);

        $warehouseIds = array_values(array_filter(
            $validated['warehouse_ids'] ?? [],
            fn ($id) => $id !== null && $id !== '',
        ));

        try {
            $preferences->save($request->user(), [
                'warehouse_ids' => $warehouseIds,
                'item_type' => $validated['item_type'],
                'sort' => $validated['sort'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('warehouse-compare-settings.edit')
            ->with('success', 'Warehouse compare defaults saved.');
    }
}
