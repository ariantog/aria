<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\Items\LegacyItemConverterService;
use App\Services\Items\SpecialSkuConverterRules;
use App\Services\Items\SpecialSkuConverterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SpecialSkuConverterController extends Controller
{
    public function __construct(
        protected SpecialSkuConverterService $converterService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeConverter();

        $currentPage = max(1, (int) $request->query('page', 1));
        $pendingCount = $this->converterService->countEligible();
        $dataList = $this->converterService->paginatePending($pendingCount);
        $previews = $this->converterService->previewItems(collect($dataList->items()));

        return view('items.special-converter', [
            'families' => SpecialSkuConverterRules::families(),
            'pendingCount' => $pendingCount,
            'dataList' => $dataList,
            'previews' => $previews->keyBy(fn (array $row) => $row['item']->id),
            'pageSize' => SpecialSkuConverterService::PAGE_SIZE,
            'currentPage' => $currentPage,
            'currentPageCount' => $dataList->count(),
            'convertiblePageCount' => collect($dataList->items())
                ->filter(fn (Item $item) => $this->converterService->isPendingConversion($item))
                ->count(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $items = $this->validatedPageItems($request);
        $preview = $this->converterService->previewItems($items);
        $successes = $preview->filter(fn (array $row) => $row['parse']->success)->count();
        $failures = $preview->count() - $successes;
        $page = $this->validatedPage($request);

        return redirect()
            ->route('items.special-converter', ['page' => $page])
            ->with('success', "Page {$page} preview: {$preview->count()} items — {$successes} ready, {$failures} would fail.");
    }

    public function run(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $items = $this->validatedPageItems($request);
        $page = $this->validatedPage($request);
        $run = $this->converterService->runItems($items, $request->user());

        return redirect()
            ->route('items.special-converter', ['page' => $page])
            ->with('success', "Converted {$run->success_count} item(s): {$run->failed_count} failed, {$run->skipped_count} skipped.");
    }

    /**
     * @return \Illuminate\Support\Collection<int, Item>
     */
    protected function validatedPageItems(Request $request): \Illuminate\Support\Collection
    {
        $request->validate([
            'item_ids' => 'required|array|min:1|max:'.SpecialSkuConverterService::PAGE_SIZE,
            'item_ids.*' => 'integer|distinct',
        ]);

        $items = $this->converterService->itemsForIds(
            array_map('intval', $request->input('item_ids')),
        );

        if ($items->isEmpty()) {
            abort(422, 'No special SKUs selected for conversion.');
        }

        return $items;
    }

    protected function validatedPage(Request $request): int
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        return max(1, (int) $request->input('page', 1));
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
