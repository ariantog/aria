<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Omnichannel;
use App\Models\ShopeeStock;
use App\Models\TikTokShop;
use App\Models\TiktokShopSync;
use App\Services\Marketplace\MarketplaceWarehouseSyncService;
use App\Services\Shopee\ShopeeStockApiService;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MarketplaceWarehouseSyncController extends Controller
{
    public function __construct(
        private MarketplaceWarehouseSyncService $syncService,
        private TikTokShopOpenApiService $tiktokOpenApi,
        private TikTokShopOAuthService $tiktokOauth,
        private ShopeeStockApiService $shopeeStockApi,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $channel = strtolower(trim((string) $request->query('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)));
        if (! in_array($channel, [MarketplaceWarehouseSync::CHANNEL_SHOPEE, MarketplaceWarehouseSync::CHANNEL_TIKTOK], true)) {
            $channel = MarketplaceWarehouseSync::CHANNEL_TIKTOK;
        }

        $q = trim((string) $request->query('q', ''));

        $shopsQuery = MarketplaceWarehouseSync::query()->where('channel', $channel);
        if ($q !== '') {
            $like = '%'.str_replace(' ', '%', $q).'%';
            $shopsQuery->where(function ($builder) use ($like): void {
                $builder->where('shop_ref', 'LIKE', $like)
                    ->orWhere('shop_name', 'LIKE', $like)
                    ->orWhere('platform_warehouse_name', 'LIKE', $like);
            });
        }

        $shopSummaries = $shopsQuery
            ->select([
                'shop_ref',
                'channel',
                DB::raw('MAX(shop_cipher) as shop_cipher'),
                DB::raw('MAX(shop_name) as shop_name'),
                DB::raw('COUNT(*) as warehouse_count'),
                DB::raw('SUM(CASE WHEN warehouse_id = 0 THEN 1 ELSE 0 END) as unmapped_count'),
                DB::raw('MAX(last_synced_at) as last_synced_at'),
            ])
            ->groupBy('shop_ref', 'channel')
            ->orderByDesc(DB::raw('MAX(updated_at)'))
            ->paginate(50)
            ->withQueryString();

        return view('marketplace.warehouse-sync.index', [
            'channel' => $channel,
            'shopSummaries' => $shopSummaries,
            'filters' => $request->only(['q', 'channel']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function create(Request $request, string $channel): View
    {
        $channel = $this->normalizeChannel($channel);
        $this->authorizeChannel($channel);

        if ($channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
            return view('marketplace.warehouse-sync.create-tiktok', array_merge(
                $this->tiktokPartnerData(),
                ['channel' => $channel, 'flash' => ['success' => session('success'), 'error' => session('errorMessage')]],
            ));
        }

        return view('marketplace.warehouse-sync.create-shopee', [
            'channel' => $channel,
            'connectionReady' => $this->shopeeStockApi->isReady(),
            'flash' => ['success' => session('success'), 'error' => session('errorMessage')],
        ]);
    }

    public function store(Request $request, string $channel): RedirectResponse
    {
        $channel = $this->normalizeChannel($channel);
        $this->authorizeChannel($channel);

        if ($channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
            $validated = $request->validate([
                'shop_ref' => 'required|string|max:64',
                'shop_cipher' => 'required|string|max:64',
                'shop_name' => 'nullable|string|max:255',
            ]);

            $count = $this->syncService->refreshTikTokShop(
                trim($validated['shop_ref']),
                trim($validated['shop_cipher']),
                trim((string) ($validated['shop_name'] ?? '')),
            );

            if ($count === 0) {
                return redirect()->back()->withInput()->with('errorMessage', $this->tiktokWarehouseListFailureMessage());
            }

            return redirect()
                ->route('marketplace.warehouse-sync.matrix', [
                    'channel' => $channel,
                    'shopRef' => trim($validated['shop_ref']),
                ])
                ->with('success', 'Gudang platform dimuat.');
        }

        $count = $this->syncService->refreshShopeeFromOAuth();
        if ($count === 0) {
            return redirect()
                ->back()
                ->with('errorMessage', 'Shopee API tidak mengembalikan gudang — authorize STOCK CHECKER OAuth.');
        }

        $oauth = app(\App\Services\Shopee\ShopeeStockOpenApiService::class)->getConnectionStatus();
        $shopRef = (string) ($oauth['shop_id'] ?? '_oauth');

        return redirect()
            ->route('marketplace.warehouse-sync.matrix', ['channel' => $channel, 'shopRef' => $shopRef !== '' ? $shopRef : '_oauth'])
            ->with('success', 'Gudang Shopee dimuat ('.$count.' baris).');
    }

    public function matrix(Request $request, string $channel, string $shopRef): View|RedirectResponse
    {
        $channel = $this->normalizeChannel($channel);
        $this->authorizeChannel($channel);

        $shopRef = trim($shopRef);
        $rowsQuery = MarketplaceWarehouseSync::query()
            ->with('warehouse')
            ->where('channel', $channel)
            ->orderBy('platform_warehouse_name')
            ->orderBy('platform_warehouse_id');

        if ($shopRef !== '_oauth') {
            $rowsQuery->where('shop_ref', $shopRef);
        } else {
            $rowsQuery->where('shop_ref', '');
        }

        $rows = $rowsQuery->get();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('marketplace.warehouse-sync.create', ['channel' => $channel])
                ->with('errorMessage', 'Belum ada gudang — sinkron dari API dulu.');
        }

        $warehouses = Addrbook::query()
            ->where('type', Addrbook::TYPE_WAREHOUSE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('marketplace.warehouse-sync.matrix', [
            'channel' => $channel,
            'shopRef' => $shopRef,
            'rows' => $rows,
            'shopName' => (string) ($rows->first()->shop_name ?? ''),
            'shopCipher' => (string) ($rows->first()->shop_cipher ?? ''),
            'warehouses' => $warehouses,
            'connectionReady' => $channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK
                ? $this->tiktokPartnerListReady()
                : $this->shopeeStockApi->isReady(),
            'showCommerceColumn' => $channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function updateMatrix(Request $request, string $channel, string $shopRef): RedirectResponse
    {
        $channel = $this->normalizeChannel($channel);
        $this->authorizeChannel($channel);

        $shopRef = trim($shopRef);

        $validated = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.id' => ['required', 'integer'],
            'mappings.*.warehouse_id' => ['nullable', 'integer', 'min:0'],
            'mappings.*.commerce_platform' => ['nullable', 'string', 'max:32'],
        ]);

        foreach ($validated['mappings'] as $row) {
            $syncRow = MarketplaceWarehouseSync::query()
                ->where('channel', $channel)
                ->where('id', (int) $row['id'])
                ->when($shopRef !== '_oauth', fn ($q) => $q->where('shop_ref', $shopRef))
                ->when($shopRef === '_oauth', fn ($q) => $q->where('shop_ref', ''))
                ->first();

            if ($syncRow === null) {
                continue;
            }

            $commercePlatform = strtoupper(trim((string) ($row['commerce_platform'] ?? '')));
            if (! in_array($commercePlatform, ['', 'TIKTOK_SHOP', 'TOKOPEDIA'], true)) {
                $commercePlatform = '';
            }

            $this->syncService->applyMatrixRowUpdate(
                $syncRow,
                (int) ($row['warehouse_id'] ?? 0),
                $commercePlatform,
            );
        }

        return redirect()
            ->route('marketplace.warehouse-sync.matrix', ['channel' => $channel, 'shopRef' => $shopRef])
            ->with('success', 'Mapping gudang disimpan.');
    }

    public function storeManualTikTok(Request $request): RedirectResponse
    {
        $this->authorizeChannel(MarketplaceWarehouseSync::CHANNEL_TIKTOK);

        $validated = $request->validate([
            'shop_ref' => 'required|string|max:64',
            'shop_cipher' => 'required|string|max:64',
            'shop_name' => 'nullable|string|max:255',
            'platform_warehouse_id' => 'required|string|max:64',
            'platform_warehouse_name' => 'nullable|string|max:255',
        ]);

        $shopRef = trim($validated['shop_ref']);
        $shopCipher = trim($validated['shop_cipher']);

        $this->syncService->createManualTikTokWarehouse(
            $shopRef,
            $shopCipher,
            trim((string) ($validated['shop_name'] ?? '')),
            trim($validated['platform_warehouse_id']),
            trim((string) ($validated['platform_warehouse_name'] ?? '')),
        );

        return redirect()
            ->route('marketplace.warehouse-sync.matrix', [
                'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
                'shopRef' => $shopRef,
            ])
            ->with('success', 'Baris gudang manual ditambahkan — petakan ke gudang Aria.');
    }

    public function storeManualTikTokMatrixRow(Request $request, string $shopRef): RedirectResponse
    {
        $this->authorizeChannel(MarketplaceWarehouseSync::CHANNEL_TIKTOK);

        $shopRef = trim($shopRef);
        $anchor = MarketplaceWarehouseSync::query()
            ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
            ->where('shop_ref', $shopRef)
            ->first();

        $validated = $request->validate([
            'shop_cipher' => 'nullable|string|max:64',
            'shop_name' => 'nullable|string|max:255',
            'platform_warehouse_id' => 'required|string|max:64',
            'platform_warehouse_name' => 'nullable|string|max:255',
        ]);

        $shopCipher = trim((string) ($validated['shop_cipher'] ?? ($anchor->shop_cipher ?? '')));
        $shopName = trim((string) ($validated['shop_name'] ?? ($anchor->shop_name ?? '')));

        $this->syncService->createManualTikTokWarehouse(
            $shopRef,
            $shopCipher,
            $shopName,
            trim($validated['platform_warehouse_id']),
            trim((string) ($validated['platform_warehouse_name'] ?? '')),
        );

        return redirect()
            ->route('marketplace.warehouse-sync.matrix', [
                'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
                'shopRef' => $shopRef,
            ])
            ->with('success', 'Baris gudang manual ditambahkan.');
    }

    public function refreshMatrix(string $channel, string $shopRef): RedirectResponse
    {
        $channel = $this->normalizeChannel($channel);
        $this->authorizeChannel($channel);

        $shopRef = trim($shopRef);

        if ($channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
            $row = MarketplaceWarehouseSync::query()
                ->where('channel', $channel)
                ->where('shop_ref', $shopRef)
                ->first();

            if ($row === null) {
                return redirect()
                    ->route('marketplace.warehouse-sync.create', ['channel' => $channel])
                    ->with('errorMessage', 'Shop tidak dikenal.');
            }

            $count = $this->syncService->refreshTikTokShop(
                $shopRef,
                (string) $row->shop_cipher,
                (string) $row->shop_name,
            );
        } else {
            $count = $this->syncService->refreshShopeeFromOAuth();
        }

        if ($count === 0) {
            $message = $channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK
                ? $this->tiktokWarehouseListFailureMessage()
                : 'Tidak ada gudang dari API.';

            return redirect()
                ->route('marketplace.warehouse-sync.matrix', ['channel' => $channel, 'shopRef' => $shopRef])
                ->with('errorMessage', $message);
        }

        return redirect()
            ->route('marketplace.warehouse-sync.matrix', ['channel' => $channel, 'shopRef' => $shopRef])
            ->with('success', 'Disinkron '.$count.' gudang.');
    }

    private function authorizeView(Request $request): void
    {
        if (Gate::check(TikTokShop::getPermissions()['sync'])
            || Gate::check(ShopeeStock::getPermissions()['sync'])
            || Omnichannel::userCanSettingsEdit($request->user())) {
            return;
        }

        abort(403);
    }

    private function authorizeChannel(string $channel): void
    {
        if ($channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
            if (Gate::check(TikTokShop::getPermissions()['sync'])) {
                return;
            }
            abort_unless(Gate::check(Omnichannel::getPermissions()['settings-edit']), 403);

            return;
        }

        Gate::authorize(ShopeeStock::getPermissions()['sync']);
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return $channel === MarketplaceWarehouseSync::CHANNEL_SHOPEE
            ? MarketplaceWarehouseSync::CHANNEL_SHOPEE
            : MarketplaceWarehouseSync::CHANNEL_TIKTOK;
    }

    private function tiktokPartnerListReady(): bool
    {
        return $this->tiktokOpenApi->partnerListReady()
            && ($this->tiktokOpenApi->isMock() || $this->tiktokOauth->hasShopAuthorization());
    }

    private function tiktokWarehouseListFailureMessage(): string
    {
        $err = $this->tiktokOpenApi->getLastOpenApiError();
        $scopeHint = 'Aktifkan scope seller.logistics di TikTok Partner Center, lalu authorize ulang OAuth TikTok Shop '
            .'(lihat doc/tiktok-shop-webhook.md — Troubleshooting sinkron gudang).';

        if ($err === null) {
            return 'Partner API tidak mengembalikan gudang (respons kosong). '.$scopeHint;
        }

        $parts = [];
        if (isset($err['http_status']) && $err['http_status'] !== null) {
            $parts[] = 'HTTP '.$err['http_status'];
        }
        if (filled($err['code'] ?? null)) {
            $parts[] = 'code '.$err['code'];
        }
        if (filled($err['message'] ?? null)) {
            $parts[] = (string) $err['message'];
        }
        if (filled($err['path'] ?? null)) {
            $parts[] = (string) $err['path'];
        }

        $detail = $parts !== [] ? implode(' · ', $parts) : 'Partner API gagal';

        return $detail.'. '.$scopeHint;
    }

    /**
     * @return array{connectionReady: bool, oauthAuthorized: bool, authorizedShops: list<array{id: string, cipher: string, name: string}>}
     */
    private function tiktokPartnerData(): array
    {
        $oauthAuthorized = $this->tiktokOauth->hasShopAuthorization();
        $connectionReady = $this->tiktokPartnerListReady();

        return [
            'connectionReady' => $connectionReady,
            'oauthAuthorized' => $oauthAuthorized,
            'authorizedShops' => $connectionReady ? $this->tiktokOpenApi->listAuthorizedShops() : [],
        ];
    }
}