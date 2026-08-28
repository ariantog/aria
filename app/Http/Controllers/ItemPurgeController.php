<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ItemPurgeController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        DataRetentionRun::authorizeManage();

        $cutoffYear = (int) request()->query('cutoff_year', $retention->liveRetentionStartYear());
        $itemType = request()->query('item_type');
        $itemType = $itemType !== null && $itemType !== '' ? (int) $itemType : null;
        $keepIds = $this->normalizeKeepIds(request()->query('keep', []));

        $preview = $retention->previewSelectableItemPurge(
            $cutoffYear,
            $itemType,
            ignoreWarehouseStock: true,
            ignoreCreatedAtCutoff: true,
            perPage: 100,
        )->appends(array_filter([
            'cutoff_year' => $cutoffYear,
            'item_type' => $itemType,
            'keep' => $keepIds !== [] ? $keepIds : null,
        ], fn ($value) => $value !== null && $value !== ''));

        $purgeCount = $retention->countSelectableOrphanItems(
            $cutoffYear,
            $itemType,
            ignoreWarehouseStock: true,
            ignoreCreatedAtCutoff: true,
            excludeItemIds: $keepIds,
        );

        return view('system-settings.item-purge', [
            'retentionYears' => $retention->retentionYears(),
            'liveStartYear' => $retention->liveRetentionStartYear(),
            'cutoffYear' => $cutoffYear,
            'itemType' => $itemType,
            'keepIds' => $keepIds,
            'purgeCount' => $purgeCount,
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
        DataRetentionRun::authorizeManage();

        $validated = $request->validate([
            'cutoff_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'item_type' => ['nullable', 'integer'],
            'keep_ids' => ['nullable', 'array'],
            'keep_ids.*' => ['integer', 'min:1'],
            'confirm' => ['required', 'string', 'in:PURGE-ITEMS-WITH-STOCK'],
        ]);

        $cutoffYear = (int) $validated['cutoff_year'];
        $itemType = isset($validated['item_type']) ? (int) $validated['item_type'] : null;
        $keepIds = $this->normalizeKeepIds($validated['keep_ids'] ?? []);

        try {
            $result = $retention->purgeOrphanItemsFromLive(
                dryRun: false,
                ignoreWarehouseStock: true,
                cutoffYear: $cutoffYear,
                itemType: $itemType,
                ignoreCreatedAtCutoff: true,
                excludeItemIds: $keepIds,
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
                'Purged %d item(s) and %d item group(s). %d item(s) were kept.',
                $result['items'],
                $result['groups'],
                count($keepIds),
            ));
    }

    /**
     * @param  array<int|string>|int|string|null  $ids
     * @return list<int>
     */
    private function normalizeKeepIds(array|int|string|null $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
