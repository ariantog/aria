<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ItemPurgeController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        DataRetentionRun::authorizeManage();

        $maxId = (int) request()->query('max_id', 0);
        if ($maxId <= 0) {
            $maxId = (int) (DB::table('items')->max('id') ?? 0);
        }

        $itemType = request()->query('item_type');
        $itemType = $itemType !== null && $itemType !== '' ? (int) $itemType : null;
        $keepIds = $this->normalizeKeepIds(request()->query('keep', []));

        $preview = $retention->previewSelectableItemPurge(
            $maxId,
            $itemType,
            perPage: 100,
        )->appends(array_filter([
            'max_id' => $maxId > 0 ? $maxId : null,
            'item_type' => $itemType,
            'keep' => $keepIds !== [] ? $keepIds : null,
        ], fn ($value) => $value !== null && $value !== ''));

        $totalCandidates = $retention->countSelectableOrphanItems($maxId, $itemType);
        $purgeCount = $retention->countSelectableOrphanItems($maxId, $itemType, $keepIds);

        return view('system-settings.item-purge', [
            'maxId' => $maxId,
            'itemType' => $itemType,
            'keepIds' => $keepIds,
            'totalCandidates' => $totalCandidates,
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
            'max_id' => ['required', 'integer', 'min:1'],
            'item_type' => ['nullable', 'integer'],
            'keep_ids' => ['nullable', 'array'],
            'keep_ids.*' => ['integer', 'min:1'],
            'confirm' => ['required', 'string', 'in:PURGE-SELECTED-ITEMS'],
        ]);

        $maxId = (int) $validated['max_id'];
        $itemType = isset($validated['item_type']) ? (int) $validated['item_type'] : null;
        $keepIds = $this->normalizeKeepIds($validated['keep_ids'] ?? []);

        try {
            $result = $retention->purgeSelectableOrphanItemsFromLive(
                maxId: $maxId,
                itemType: $itemType,
                excludeItemIds: $keepIds,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('data-retention.item-purge.index', array_filter([
                'max_id' => $maxId,
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
