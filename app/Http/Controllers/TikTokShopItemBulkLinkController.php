<?php

namespace App\Http\Controllers;

use App\Models\TikTokShop;
use App\Services\TikTokShop\TikTokShopItemBulkLinkService;
use App\Services\TikTokShop\TikTokShopItemSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TikTokShopItemBulkLinkController extends Controller
{
    public function index(
        TikTokShopItemSearchService $searchService,
        TikTokShopItemBulkLinkService $service,
    ): View {
        $this->authorizeBulkLink();

        $runningRun = $service->activeRun();
        $displayRunModel = $service->displayRun();
        $displayRun = $displayRunModel !== null ? $service->runForDisplay($displayRunModel) : null;

        $previewToken = (string) session('tiktok_bulk_link_preview_token', '');
        $preview = $previewToken !== '' ? $service->previewForToken($previewToken) : null;
        if ($preview === null && $previewToken !== '') {
            session()->forget('tiktok_bulk_link_preview_token');
        }

        $resultRows = array_merge(
            $preview['rows'] ?? [],
            $displayRun['failed_rows'] ?? [],
        );

        return view('tiktok-shop.bulk-link.index', [
            'oauthReady' => $searchService->isReady(),
            'preview' => $preview,
            'displayRun' => $displayRun,
            'bulkLinkRunning' => $runningRun !== null,
            'itemModels' => $service->loadItemsForResultRows($resultRows),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function preview(Request $request, TikTokShopItemBulkLinkService $service): RedirectResponse
    {
        $this->authorizeBulkLink();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        try {
            $result = $service->preview($validated['file']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        session(['tiktok_bulk_link_preview_token' => $result['token']]);

        return redirect()->route('tiktok-shop.bulk-link.index');
    }

    public function apply(Request $request, TikTokShopItemBulkLinkService $service): RedirectResponse
    {
        $this->authorizeBulkLink();

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $run = $service->startApply(
                $validated['token'],
                (bool) ($validated['overwrite_existing'] ?? false),
                (int) ($request->user()?->id ?? 0),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        session()->forget('tiktok_bulk_link_preview_token');

        $remaining = max(0, $run->total_rows - $run->processed_rows);
        $batchesLeft = (int) ceil($remaining / TikTokShopItemBulkLinkService::BATCH_SIZE);

        return redirect()
            ->route('tiktok-shop.bulk-link.index')
            ->with('success', sprintf(
                'Bulk link dimulai: batch pertama selesai (%d / %d baris). ~%d batch berikutnya jalan otomatis (max %d baris/menit).',
                $run->processed_rows,
                $run->total_rows,
                $batchesLeft,
                TikTokShopItemBulkLinkService::BATCH_SIZE,
            ));
    }

    private function authorizeBulkLink(): void
    {
        if (Gate::any([
            TikTokShop::getPermissions()['item_link'],
            TikTokShop::getPermissions()['view'],
        ])) {
            return;
        }

        abort(403);
    }
}