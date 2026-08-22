<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemIdentityConversionResult;
use App\Models\ItemIdentityConversionRun;
use App\Services\Items\LegacyItemConverterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LegacyItemConverterController extends Controller
{
    public function __construct(
        protected LegacyItemConverterService $converterService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeConverter();

        $tab = $request->query('tab', 'pending');
        $itemType = ItemType::tryFrom((int) $request->query('type', ItemType::ASSET_LANCAR->value))
            ?? ItemType::ASSET_LANCAR;
        $currentPage = max(1, (int) $request->query('page', 1));

        $queueStats = $this->converterService->queueStats($itemType);
        $uselessCount = $this->converterService->uselessQuery($itemType)->count();
        $superOldCount = $this->converterService->superOldQuery($itemType)->count();
        $unparseableCount = $queueStats['unparseable'];
        $latestRun = ItemIdentityConversionRun::query()
            ->where('item_type', $itemType)
            ->latest('id')
            ->first();

        $data = match ($tab) {
            'completed' => $this->completedResults($itemType),
            'failed' => $this->failedResults($itemType),
            default => $this->pendingItems($itemType, $queueStats['eligible']),
        };

        return view('items.legacy-converter', [
            'tab' => $tab,
            'itemType' => $itemType,
            'pendingCount' => $queueStats['eligible'],
            'candidateCount' => $queueStats['candidates'],
            'uselessCount' => $uselessCount,
            'superOldCount' => $superOldCount,
            'unparseableCount' => $unparseableCount,
            'latestRun' => $latestRun,
            'dataList' => $data,
            'batchSize' => LegacyItemConverterService::DEFAULT_BATCH_SIZE,
            'pageSize' => LegacyItemConverterService::PENDING_PAGE_SIZE,
            'currentPage' => $currentPage,
            'currentPageCount' => $tab === 'pending' ? $data->count() : 0,
            'convertiblePageCount' => $tab === 'pending'
                ? collect($data->items())->filter(fn (Item $item) => $this->converterService->isPendingConversion($item))->count()
                : 0,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $itemType = $this->validatedItemType($request);
        $items = $this->validatedPageItems($request, $itemType);
        $preview = $this->converterService->previewItems($items);

        $successes = $preview->filter(fn (array $row) => $row['parse']->success)->count();
        $failures = $preview->count() - $successes;
        $page = $this->validatedPage($request);

        return redirect()
            ->route('items.legacy-converter', [
                'tab' => 'pending',
                'type' => $itemType->value,
                'page' => $page,
            ])
            ->with('success', "Page {$page} preview: {$preview->count()} items — {$successes} parseable, {$failures} would fail.");
    }

    public function run(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $itemType = $this->validatedItemType($request);
        $items = $this->validatedPageItems($request, $itemType);
        $page = $this->validatedPage($request);
        $run = $this->converterService->runItems($itemType, $items, $request->user());

        $redirectTab = $run->failed_count > 0 && $run->success_count === 0 && $run->skipped_count === 0
            ? 'failed'
            : 'pending';

        return redirect()
            ->route('items.legacy-converter', [
                'tab' => $redirectTab,
                'type' => $itemType->value,
                'page' => $page,
            ])
            ->with('success', "Converted {$run->success_count} item(s): {$run->failed_count} failed, {$run->skipped_count} skipped.");
    }

    public function purgeUseless(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $itemType = $this->validatedItemType($request);
        $deleted = $this->converterService->deleteUselessBatch($itemType);

        return redirect()
            ->route('items.legacy-converter', [
                'tab' => 'pending',
                'type' => $itemType->value,
            ])
            ->with('success', "Hard-deleted {$deleted} useless SKU(s) (created >1 year ago, never used in transactions).");
    }

    protected function pendingItems(ItemType $itemType, ?int $eligibleTotal = null)
    {
        return $this->converterService
            ->paginateEligible($itemType, LegacyItemConverterService::PENDING_PAGE_SIZE, $eligibleTotal)
            ->withQueryString();
    }

    protected function completedResults(ItemType $itemType)
    {
        return ItemIdentityConversionResult::query()
            ->with(['item', 'run'])
            ->where('status', ItemIdentityConversionResult::STATUS_SUCCESS)
            ->whereHas('item', fn ($q) => $q->where('type', $itemType)->whereNull('deleted_at'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();
    }

    protected function failedResults(ItemType $itemType)
    {
        return ItemIdentityConversionResult::query()
            ->with(['item', 'run'])
            ->where('status', ItemIdentityConversionResult::STATUS_FAILED)
            ->whereHas('item', fn ($q) => $q->where('type', $itemType)->whereNull('deleted_at'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();
    }

    protected function validatedItemType(Request $request): ItemType
    {
        $request->validate([
            'type' => 'required|integer|in:'.ItemType::ITEM->value.','.ItemType::ASSET_LANCAR->value,
        ]);

        return ItemType::from((int) $request->input('type'));
    }

    protected function validatedPage(Request $request): int
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        return max(1, (int) $request->input('page', 1));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Item>
     */
    protected function validatedPageItems(Request $request, ItemType $itemType): \Illuminate\Support\Collection
    {
        $request->validate([
            'item_ids' => 'required|array|min:1|max:'.LegacyItemConverterService::PENDING_PAGE_SIZE,
            'item_ids.*' => 'integer|distinct',
        ]);

        $items = $this->converterService->itemsForIds(
            $itemType,
            array_map('intval', $request->input('item_ids')),
        );

        if ($items->isEmpty()) {
            abort(422, 'No convertible items selected (empty Legacy column required).');
        }

        return $items;
    }

    protected function authorizeConverter(): void
    {
        $user = auth()->user();

        if ($user?->is_superadmin) {
            return;
        }

        Gate::authorize(Item::getPermissions()['convert-legacy']);
    }
}
