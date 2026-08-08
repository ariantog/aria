<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemIdentityConversionResult;
use App\Models\ItemIdentityConversionRun;
use App\Services\Items\LegacyItemConverterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $pendingCount = $this->converterService->eligibleQuery($itemType)->count();
        $latestRun = ItemIdentityConversionRun::query()
            ->where('item_type', $itemType)
            ->latest('id')
            ->first();

        $data = match ($tab) {
            'completed' => $this->completedResults($itemType),
            'failed' => $this->failedResults($itemType),
            default => $this->pendingItems($itemType),
        };

        return view('items.legacy-converter', [
            'tab' => $tab,
            'itemType' => $itemType,
            'pendingCount' => $pendingCount,
            'latestRun' => $latestRun,
            'dataList' => $data,
            'batchSize' => LegacyItemConverterService::DEFAULT_BATCH_SIZE,
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
        $preview = $this->converterService->previewBatch($itemType);

        $successes = $preview->filter(fn (array $row) => $row['parse']->success)->count();
        $failures = $preview->count() - $successes;

        return redirect()
            ->route('items.legacy-converter', [
                'tab' => 'pending',
                'type' => $itemType->value,
            ])
            ->with('success', "Preview: {$preview->count()} items — {$successes} parseable, {$failures} would fail.");
    }

    public function run(Request $request): RedirectResponse
    {
        $this->authorizeConverter();

        $itemType = $this->validatedItemType($request);
        $run = $this->converterService->runBatch($itemType, $request->user(), dryRun: false);

        return redirect()
            ->route('items.legacy-converter', [
                'tab' => $run->failed_count > 0 ? 'failed' : 'completed',
                'type' => $itemType->value,
            ])
            ->with('success', "Batch complete: {$run->success_count} converted, {$run->failed_count} failed, {$run->skipped_count} skipped.");
    }

    protected function pendingItems(ItemType $itemType)
    {
        return $this->converterService
            ->eligibleQuery($itemType)
            ->paginate(50)
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

    protected function authorizeConverter(): void
    {
        $user = auth()->user();

        if (! $user?->is_superadmin && ! $user?->can('items-convert-legacy')) {
            abort(403);
        }
    }
}
