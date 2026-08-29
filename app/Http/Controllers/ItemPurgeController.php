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

        $preview = $retention->previewSelectableItemPurge(
            $maxId,
            $itemType,
            perPage: 100,
        )->appends(array_filter([
            'max_id' => $maxId > 0 ? $maxId : null,
            'item_type' => $itemType,
        ], fn ($value) => $value !== null && $value !== ''));

        $totalCandidates = $retention->countSelectableOrphanItems($maxId, $itemType);

        return view('system-settings.item-purge', [
            'maxId' => $maxId,
            'itemType' => $itemType,
            'totalCandidates' => $totalCandidates,
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
            'page' => ['nullable', 'integer', 'min:1'],
            'page_item_ids' => ['required', 'array', 'min:1'],
            'page_item_ids.*' => ['integer', 'min:1'],
            'keep_ids' => ['nullable', 'array'],
            'keep_ids.*' => ['integer', 'min:1'],
            'confirm' => ['required', 'string', 'in:PURGE-SELECTED-ITEMS'],
        ]);

        $maxId = (int) $validated['max_id'];
        $itemType = isset($validated['item_type']) ? (int) $validated['item_type'] : null;
        $page = isset($validated['page']) ? (int) $validated['page'] : 1;
        $pageItemIds = $this->normalizeKeepIds($validated['page_item_ids']);
        $keepIds = $this->normalizeKeepIds($validated['keep_ids'] ?? []);
        $purgeIds = array_values(array_diff($pageItemIds, $keepIds));

        if ($purgeIds === []) {
            return back()->with('error', 'No items selected for purge on this page.');
        }

        try {
            $result = $retention->purgeSelectableOrphanItemsByIds(
                maxId: $maxId,
                itemType: $itemType,
                itemIds: $purgeIds,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('data-retention.item-purge.index', array_filter([
                'max_id' => $maxId,
                'item_type' => $itemType,
                'page' => $page > 1 ? $page : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', sprintf(
                'Purged %d item(s) and %d item group(s) from page %d. %d item(s) on this page were kept. Other pages were not changed.',
                $result['items'],
                $result['groups'],
                $page,
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
