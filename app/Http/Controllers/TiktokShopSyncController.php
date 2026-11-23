<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Omnichannel;
use App\Models\TikTokShop;
use App\Models\TiktokShopSync;
use App\Models\TiktokShopWarehouseSync;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use App\Services\TikTokShop\TikTokShopWarehouseSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TiktokShopSyncController extends Controller
{
    public function __construct(
        private TikTokShopOpenApiService $openApi,
        private TikTokShopOAuthService $oauth,
        private TikTokShopWarehouseSyncService $warehouseSyncService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeSync();

        $q = trim((string) $request->query('q', ''));

        $shopsQuery = TiktokShopWarehouseSync::query();
        if ($q !== '') {
            $like = '%'.str_replace(' ', '%', $q).'%';
            $shopsQuery->where(function ($builder) use ($like): void {
                $builder->where('shop_ref', 'LIKE', $like)
                    ->orWhere('shop_name', 'LIKE', $like);
            });
        }

        $shopsQuery = $shopsQuery
            ->select([
                'shop_ref',
                DB::raw('MAX(shop_cipher) as shop_cipher'),
                DB::raw('MAX(shop_name) as shop_name'),
                DB::raw('COUNT(*) as warehouse_count'),
                DB::raw('SUM(CASE WHEN warehouse_id = 0 THEN 1 ELSE 0 END) as unmapped_count'),
                DB::raw('MAX(last_synced_at) as last_synced_at'),
            ])
            ->groupBy('shop_ref')
            ->orderByDesc(DB::raw('MAX(updated_at)'));

        $shopSummaries = $shopsQuery->paginate(50)->withQueryString();

        return view('tiktok-shop.sync.index', [
            'shopSummaries' => $shopSummaries,
            'filters' => $request->only(['q']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorizeSync();

        return view('tiktok-shop.sync.create', array_merge(
            $this->partnerConnectionData(),
            [
                'flash' => ['success' => session('success'), 'error' => session('errorMessage')],
            ]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSync();

        return $this->bootstrapShopAndRedirect($request);
    }

    public function edit(TiktokShopSync $sync): RedirectResponse
    {
        $this->authorizeSync();

        return redirect()->route('tiktok-shop.sync.matrix', ['shopRef' => $sync->shop_ref]);
    }

    public function update(Request $request, TiktokShopSync $sync): RedirectResponse
    {
        $this->authorizeSync();

        return redirect()->route('tiktok-shop.sync.matrix', ['shopRef' => $sync->shop_ref]);
    }

    public function destroy(TiktokShopSync $sync): RedirectResponse
    {
        $this->authorizeSync();

        $shopRef = $sync->shop_ref;
        $sync->delete();

        if (Schema::hasTable('tiktok_shop_warehouse_syncs')) {
            TiktokShopWarehouseSync::query()->where('shop_ref', $shopRef)->delete();
        }

        return redirect()->route('tiktok-shop.sync.index')->with('success', 'Mapping TikTok Shop dihapus.');
    }

    public function matrix(Request $request, string $shopRef): View|RedirectResponse
    {
        $this->authorizeSync();

        $shopRef = trim($shopRef);
        $rows = TiktokShopWarehouseSync::query()
            ->with('warehouse')
            ->where('shop_ref', $shopRef)
            ->orderBy('platform_warehouse_name')
            ->orderBy('platform_warehouse_id')
            ->get();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('tiktok-shop.sync.create')
                ->with('errorMessage', 'Belum ada gudang platform untuk shop '.$shopRef.'. Pilih toko lalu sinkron.');
        }

        $warehouses = Addrbook::query()
            ->where('type', Addrbook::TYPE_WAREHOUSE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('tiktok-shop.sync.matrix', [
            'shopRef' => $shopRef,
            'rows' => $rows,
            'shopName' => (string) ($rows->first()->shop_name ?? ''),
            'shopCipher' => (string) ($rows->first()->shop_cipher ?? ''),
            'warehouses' => $warehouses,
            'connectionReady' => $this->partnerListReady(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function updateMatrix(Request $request, string $shopRef): RedirectResponse
    {
        $this->authorizeSync();

        $shopRef = trim($shopRef);

        $validated = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.id' => ['required', 'integer'],
            'mappings.*.warehouse_id' => ['nullable', 'integer', 'min:0'],
            'mappings.*.commerce_platform' => ['nullable', 'string', 'max:32'],
        ]);

        foreach ($validated['mappings'] as $row) {
            $syncRow = TiktokShopWarehouseSync::query()
                ->where('shop_ref', $shopRef)
                ->where('id', (int) $row['id'])
                ->first();

            if ($syncRow === null) {
                continue;
            }

            $commercePlatform = strtoupper(trim((string) ($row['commerce_platform'] ?? '')));
            if (! in_array($commercePlatform, ['', 'TIKTOK_SHOP', 'TOKOPEDIA'], true)) {
                $commercePlatform = '';
            }

            $syncRow->update([
                'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                'commerce_platform' => $commercePlatform,
            ]);
        }

        return redirect()
            ->route('tiktok-shop.sync.matrix', ['shopRef' => $shopRef])
            ->with('success', 'Mapping gudang disimpan.');
    }

    public function refreshMatrix(string $shopRef): RedirectResponse
    {
        $this->authorizeSync();

        $shopRef = trim($shopRef);

        $row = TiktokShopWarehouseSync::query()->where('shop_ref', $shopRef)->first();
        if ($row === null) {
            return redirect()
                ->route('tiktok-shop.sync.create')
                ->with('errorMessage', 'Shop tidak dikenal. Pilih toko dari OAuth.');
        }

        $count = $this->warehouseSyncService->refreshForShop(
            $shopRef,
            (string) $row->shop_cipher,
            (string) $row->shop_name,
        );

        if ($count === 0) {
            return redirect()
                ->route('tiktok-shop.sync.matrix', ['shopRef' => $shopRef])
                ->with('errorMessage', 'Tidak ada gudang dari Partner API — periksa OAuth atau shop_cipher.');
        }

        return redirect()
            ->route('tiktok-shop.sync.matrix', ['shopRef' => $shopRef])
            ->with('success', 'Disinkron '.$count.' gudang dari TikTok.');
    }

    private function bootstrapShopAndRedirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_ref' => 'required|string|max:64',
            'shop_cipher' => 'required|string|max:64',
            'shop_name' => 'nullable|string|max:255',
        ]);

        $shopRef = trim($validated['shop_ref']);
        $shopCipher = trim($validated['shop_cipher']);
        $shopName = trim((string) ($validated['shop_name'] ?? ''));

        $count = $this->warehouseSyncService->refreshForShop($shopRef, $shopCipher, $shopName);

        if ($count === 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', 'Partner API tidak mengembalikan gudang — authorize OAuth atau coba lagi.');
        }

        return redirect()
            ->route('tiktok-shop.sync.matrix', ['shopRef' => $shopRef])
            ->with('success', 'Gudang platform dimuat — petakan ke gudang Aria.');
    }

    private function authorizeSync(): void
    {
        if (Gate::check(TikTokShop::getPermissions()['sync'])) {
            return;
        }

        abort_unless(Gate::check(Omnichannel::getPermissions()['settings-edit']), 403);
    }

    private function partnerListReady(): bool
    {
        return $this->openApi->partnerListReady()
            && ($this->openApi->isMock() || $this->oauth->hasShopAuthorization());
    }

    /**
     * @return array{
     *     connectionReady: bool,
     *     oauthAuthorized: bool,
     *     authorizedShops: list<array{id: string, cipher: string, name: string}>
     * }
     */
    private function partnerConnectionData(): array
    {
        $oauthAuthorized = $this->oauth->hasShopAuthorization();
        $connectionReady = $this->partnerListReady();

        return [
            'connectionReady' => $connectionReady,
            'oauthAuthorized' => $oauthAuthorized,
            'authorizedShops' => $connectionReady ? $this->openApi->listAuthorizedShops() : [],
        ];
    }
}