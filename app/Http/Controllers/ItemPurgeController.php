<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ItemPurgeController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $cutoffYear = (int) request()->query('cutoff_year', $retention->liveRetentionStartYear());
        $itemType = request()->query('item_type');
        $itemType = $itemType !== null && $itemType !== '' ? (int) $itemType : null;

        $preview = $retention->previewSelectableItemPurge(
            $cutoffYear,
            $itemType,
            ignoreWarehouseStock: true,
        );

        return view('system-settings.item-purge', [
            'retentionYears' => $retention->retentionYears(),
            'liveStartYear' => $retention->liveRetentionStartYear(),
            'cutoffYear' => $cutoffYear,
            'itemType' => $itemType,
            'itemTypes' => [
                ItemType::ITEM->value => 'Item',
                ItemType::ASSET_LANCAR->value => 'Asset Lancar',
            ],
            'preview' => $preview,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function purge(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'cutoff_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'item_type' => ['nullable', 'integer'],
            'confirm' => ['required', 'string', 'in:PURGE-ITEMS-WITH-STOCK'],
        ]);

        $cutoffYear = (int) $validated['cutoff_year'];
        $itemType = isset($validated['item_type']) ? (int) $validated['item_type'] : null;

        try {
            $result = $retention->purgeOrphanItemsFromLive(
                dryRun: false,
                ignoreWarehouseStock: true,
                cutoffYear: $cutoffYear,
                itemType: $itemType,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('data-retention.item-purge.index', array_filter([
                'cutoff_year' => $cutoffYear,
                'item_type' => $itemType,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', sprintf(
                'Purged %d item(s) and %d item group(s) created before %d (warehouse stock ignored).',
                $result['items'],
                $result['groups'],
                $cutoffYear,
            ));
    }
}
