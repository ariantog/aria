<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Jubelio;
use App\Services\Jubelio\JubelioItemAutoLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JubelioItemAutoLinkController extends Controller
{
    public function index(JubelioItemAutoLinkService $service): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $runner = $service->runner();

        return view('jubelio.item-auto-link.index', [
            'runner' => $runner,
            'stats' => $service->dashboardStats(),
            'attempts' => $service->recentAttempts(50),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function pause(JubelioItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $service->pause();

        return back()->with('success', 'Auto-link cron paused.');
    }

    public function resume(JubelioItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $service->resume();

        return back()->with('success', 'Auto-link cron resumed.');
    }

    public function runBatch(Request $request, JubelioItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? JubelioItemAutoLinkService::DEFAULT_BATCH_PER_RUN);
        $result = $service->processBatch($limit);

        return back()->with('success', sprintf(
            'Manual batch: %d processed, %d linked (%d API calls left this hour).',
            $result['processed'],
            $result['linked'],
            $result['remaining_budget'],
        ));
    }

    public function resetItem(Item $item, JubelioItemAutoLinkService $service): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $service->resetAttemptsForItem($item->id);

        return back()->with('success', 'Attempt history cleared for '.$item->code.'.');
    }
}
