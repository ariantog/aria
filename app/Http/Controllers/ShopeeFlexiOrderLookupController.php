<?php

namespace App\Http\Controllers;

use App\Models\ShopeeFlexiShop;
use App\Models\ShopeeStock;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\Marketplace\ShopeeFlexiOrderLookupService;
use App\Services\Marketplace\ShopeeFlexiSellPrefillService;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ShopeeFlexiOrderLookupController extends Controller
{
    public function show(Request $request, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return $this->ordersView($shopeeApi, [
            'orderRef' => (string) $request->old('order_ref', ''),
            'shopId' => (string) $request->old('shop_id', ''),
            'loadDetail' => (bool) $request->old('load_detail', false),
            'result' => null,
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function store(Request $request, ShopeeFlexiOrderLookupService $lookup, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $loadDetail = $request->boolean('load_detail');

        return $this->ordersView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'loadDetail' => $loadDetail,
            'result' => $lookup->lookup($shopId, $orderRef, $loadDetail),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function createSell(
        Request $request,
        ShopeeFlexiOrderLookupService $lookup,
        ShopeeFlexiSellPrefillService $prefillService,
        ShopeeAdminOpenApiService $shopeeApi,
        ShopeeSyncInvoicePrefix $invoicePrefix,
        JubelioSellInvoiceGuard $sellInvoiceGuard,
    ): RedirectResponse|View {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        if (! Transaction::userCanAccessType($request->user(), 'sell')) {
            return redirect()
                ->route('shopee.flexi.orders')
                ->with('error', 'Anda tidak punya izin membuat transaksi SELL.');
        }

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $orderSn = $lookup->normalizeOrderSn($orderRef);
        $invoice = $invoicePrefix->invoiceForOrderSn($shopId, $orderSn);

        if ($sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return $this->ordersView($shopeeApi, [
                'orderRef' => $orderRef,
                'shopId' => (string) $shopId,
                'loadDetail' => true,
                'result' => $lookup->lookup($shopId, $orderRef, true),
                'flash' => [
                    'success' => null,
                    'error' => 'Invoice sudah ada di Transaksi.',
                ],
            ]);
        }

        $result = $prefillService->build($shopId, $orderSn);

        if (! ($result['success'] ?? false) || empty($result['prefill'])) {
            return $this->ordersView($shopeeApi, [
                'orderRef' => $orderRef,
                'shopId' => (string) $shopId,
                'loadDetail' => true,
                'result' => $lookup->lookup($shopId, $orderRef, true),
                'flash' => [
                    'success' => null,
                    'error' => $result['message'] ?? 'Gagal membangun prefill.',
                ],
            ]);
        }

        Session::put('transaction_sell_prefill', $result['prefill']);

        return redirect()->route('transactions.create', ['type' => 'sell']);
    }

    /**
     * @param  array{orderRef: string, shopId: string, loadDetail?: bool, result: array<string, mixed>|null, flash?: array{success: ?string, error: ?string}}  $data
     */
    private function ordersView(ShopeeAdminOpenApiService $shopeeApi, array $data): View
    {
        return view('shopee.flexi.orders', array_merge($data, [
            'authorizedShopIds' => $shopeeApi->getConnectionStatus()['authorized_shop_ids'] ?? [],
            'shopOptions' => $this->shopOptionsForLookup($shopeeApi->getConnectionStatus()['authorized_shop_ids'] ?? [], (string) ($data['shopId'] ?? '')),
            'loadDetail' => (bool) ($data['loadDetail'] ?? false),
            'flash' => $data['flash'] ?? ['success' => null, 'error' => null],
        ]));
    }

    /**
     * @param  list<int|string>  $authorizedShopIds
     * @return list<array{id: int, label: string}>
     */
    private function shopOptionsForLookup(array $authorizedShopIds, string $selectedShopId): array
    {
        $options = [];
        $prefixLabels = config('shopee_flexi.flexi_prefixes', []);

        if (Schema::hasTable('shopee_flexi_shops')) {
            $rows = ShopeeFlexiShop::query()->orderBy('shop_id')->get();
            if ($rows->isNotEmpty()) {
                foreach ($rows as $row) {
                    $prefix = strtoupper((string) $row->invoice_prefix);
                    $parts = [(string) (int) $row->shop_id, $prefix];
                    if (filled($row->label)) {
                        $parts[] = (string) $row->label;
                    } elseif (filled($prefixLabels[$prefix] ?? null)) {
                        $parts[] = (string) $prefixLabels[$prefix];
                    }
                    $options[] = [
                        'id' => (int) $row->shop_id,
                        'label' => implode(' — ', $parts),
                    ];
                }

                return $this->ensureSelectedShopOption($options, $selectedShopId);
            }
        }

        foreach ($authorizedShopIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $options[] = [
                'id' => $id,
                'label' => (string) $id.' (OAuth — belum di /shopee-flexi)',
            ];
        }

        return $this->ensureSelectedShopOption($options, $selectedShopId);
    }

    /**
     * @param  list<array{id: int, label: string}>  $options
     * @return list<array{id: int, label: string}>
     */
    private function ensureSelectedShopOption(array $options, string $selectedShopId): array
    {
        $selected = (int) $selectedShopId;
        if ($selected <= 0) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int) $option['id'] === $selected) {
                return $options;
            }
        }

        $options[] = [
            'id' => $selected,
            'label' => $selected.' (posting — tidak ada di daftar FLEXI saat ini)',
        ];

        return $options;
    }
}