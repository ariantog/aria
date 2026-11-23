<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Omnichannel;
use App\Models\TikTokShop;
use App\Models\TiktokShopSync;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TiktokShopSyncController extends Controller
{
    public function __construct(
        private TikTokShopOpenApiService $openApi,
        private TikTokShopOAuthService $oauth,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeSync();

        $query = TiktokShopSync::with(['warehouse']);

        if ($request->query('q')) {
            $q = '%'.str_replace(' ', '%', (string) $request->query('q')).'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('shop_ref', 'LIKE', $q)
                    ->orWhere('shop_name', 'LIKE', $q)
                    ->orWhere('shop_cipher', 'LIKE', $q);
            });
        }

        $dataList = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('tiktok-shop.sync.index', [
            'dataList' => $dataList,
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
            $this->syncFormPartnerData(),
            [
                'addrbookTypes' => [
                    'warehouse' => Addrbook::TYPE_WAREHOUSE,
                ],
                'flash' => ['success' => session('success'), 'error' => session('errorMessage')],
            ]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSync();

        $validated = $request->validate([
            'shop_ref' => 'required|string|max:64',
            'shop_cipher' => 'nullable|string|max:64',
            'shop_name' => 'nullable|string|max:255',
            'warehouse_id' => 'required|exists:customers,id',
        ]);

        TiktokShopSync::query()->create([
            'shop_ref' => trim($validated['shop_ref']),
            'shop_cipher' => trim((string) ($validated['shop_cipher'] ?? '')),
            'shop_name' => trim((string) ($validated['shop_name'] ?? '')),
            'warehouse_id' => (int) $validated['warehouse_id'],
        ]);

        return redirect()->route('tiktok-shop.sync.index')->with('success', 'Mapping TikTok Shop disimpan.');
    }

    public function edit(TiktokShopSync $sync): View
    {
        $this->authorizeSync();

        $sync->load(['warehouse']);

        return view('tiktok-shop.sync.edit', array_merge(
            $this->syncFormPartnerData($sync),
            [
                'sync' => $sync,
                'addrbookTypes' => [
                    'warehouse' => Addrbook::TYPE_WAREHOUSE,
                ],
                'flash' => ['success' => session('success'), 'error' => session('errorMessage')],
            ]
        ));
    }

    public function update(Request $request, TiktokShopSync $sync): RedirectResponse
    {
        $this->authorizeSync();

        $validated = $request->validate([
            'shop_ref' => 'required|string|max:64',
            'shop_cipher' => 'nullable|string|max:64',
            'shop_name' => 'nullable|string|max:255',
            'warehouse_id' => 'required|exists:customers,id',
        ]);

        $sync->update([
            'shop_ref' => trim($validated['shop_ref']),
            'shop_cipher' => trim((string) ($validated['shop_cipher'] ?? '')),
            'shop_name' => trim((string) ($validated['shop_name'] ?? '')),
            'warehouse_id' => (int) $validated['warehouse_id'],
        ]);

        return redirect()->route('tiktok-shop.sync.index')->with('success', 'Mapping TikTok Shop diperbarui.');
    }

    public function destroy(TiktokShopSync $sync): RedirectResponse
    {
        $this->authorizeSync();

        $sync->delete();

        return redirect()->route('tiktok-shop.sync.index')->with('success', 'Mapping TikTok Shop dihapus.');
    }

    private function authorizeSync(): void
    {
        if (Gate::check(TikTokShop::getPermissions()['sync'])) {
            return;
        }

        abort_unless(Gate::check(Omnichannel::getPermissions()['settings-edit']), 403);
    }

    /**
     * @return array{
     *     connectionReady: bool,
     *     oauthAuthorized: bool,
     *     authorizedShops: list<array{id: string, cipher: string, name: string}>,
     *     sellerWarehousesByCipher: array<string, list<array{id: string, name: string, type: string, effect_status: string}>>,
     *     initialSellerWarehouses: list<array{id: string, name: string, type: string, effect_status: string}>
     * }
     */
    private function syncFormPartnerData(?TiktokShopSync $sync = null): array
    {
        $oauthAuthorized = $this->oauth->hasShopAuthorization();

        $connectionReady = $this->openApi->partnerListReady()
            && ($this->openApi->isMock() || $oauthAuthorized);

        $authorizedShops = $connectionReady ? $this->openApi->listAuthorizedShops() : [];
        $sellerWarehousesByCipher = $connectionReady
            ? $this->openApi->listSellerWarehousesByShopCipher($authorizedShops)
            : [];

        $cipher = trim((string) old('shop_cipher', $sync?->shop_cipher ?? ''));
        if ($cipher === '' && $sync !== null) {
            $cipher = trim((string) $sync->shop_cipher);
        }

        $initialSellerWarehouses = [];
        if ($cipher !== '') {
            $initialSellerWarehouses = $sellerWarehousesByCipher[$cipher]
                ?? ($connectionReady ? $this->openApi->listSellerWarehouses($cipher) : []);
        }

        return [
            'connectionReady' => $connectionReady,
            'oauthAuthorized' => $oauthAuthorized,
            'authorizedShops' => $authorizedShops,
            'sellerWarehousesByCipher' => $sellerWarehousesByCipher,
            'initialSellerWarehouses' => $initialSellerWarehouses,
        ];
    }
}