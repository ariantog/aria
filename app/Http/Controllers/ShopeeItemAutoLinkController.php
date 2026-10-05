<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ShopeeStock;
use App\Services\Shopee\ShopeeItemAutoLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopeeItemAutoLinkController extends Controller
{
    public function index(ShopeeItemAutoLinkService $service): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return view('shopee.item-auto-link.index', [
            'runner' => $service->runner(),
            'stats' => $service->dashboardStats(),
            'attempts' => $service->recentAttempts(50),
            'stockReady' => app(\App\Services\Shopee\ShopeeStockApiService::class)->isReady(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function pause(ShopeeItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $service->pause();

        return back()->with('success', 'Shopee auto-link cron paused.');
    }

    public function resume(ShopeeItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $service->resume();

        return back()->with('success', 'Shopee auto-link cron resumed.');
    }

    public function runBatch(Request $request, ShopeeItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? ShopeeItemAutoLinkService::DEFAULT_BATCH_PER_RUN);
        $result = $service->processBatch($limit);

        return back()->with('success', sprintf(
            'Manual batch: %d processed, %d linked (%d API calls left this hour).',
            $result['processed'],
            $result['linked'],
            $result['remaining_budget'],
        ));
    }

    public function resetItem(Item $item, ShopeeItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $service->resetAttemptsForItem($item->id);

        return back()->with('success', 'Attempt history cleared for '.$item->code.'.');
    }
}
