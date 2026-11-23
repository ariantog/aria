<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\ShopeeFlexiShop;
use App\Models\ShopeeStock;
use Illuminate\Support\Facades\Schema;
use App\Actions\Marketplace\ProcessShopeeFlexiSellOrder;
use App\Models\Transaction;
use App\Services\Marketplace\ShopeeFlexiOrderLookupService;
use App\Services\Marketplace\ShopeeOrderPollService;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeFlexiOrderLookupController extends Controller
{
    public function show(Request $request, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $status = $shopeeApi->getConnectionStatus();

        return $this->lookupView($shopeeApi, [
            'orderRef' => (string) $request->old('order_ref', ''),
            'shopId' => (string) $request->old('shop_id', ''),
            'loadDetail' => (bool) $request->old('load_detail', false),
            'result' => null,
            'ingestResult' => null,
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

        return $this->lookupView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'loadDetail' => $loadDetail,
            'result' => $lookup->lookup($shopId, $orderRef, $loadDetail),
            'ingestResult' => null,
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function createSell(
        Request $request,
        ShopeeFlexiOrderLookupService $lookup,
        ProcessShopeeFlexiSellOrder $processor,
        ShopeeAdminOpenApiService $shopeeApi,
    ): RedirectResponse|View {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $orderSn = $lookup->normalizeOrderSn($orderRef);

        $result = $processor->execute($shopId, $orderSn, auth()->id());

        if ($result['success'] && ! empty($result['transaction_id'])) {
            $transaction = Transaction::query()->find((int) $result['transaction_id']);

            return redirect()
                ->route('transactions.show', $transaction ?? (int) $result['transaction_id'])
                ->with('success', $result['message']);
        }

        return $this->lookupView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'loadDetail' => true,
            'result' => $lookup->lookup($shopId, $orderRef, true),
            'ingestResult' => null,
            'flash' => [
                'success' => null,
                'error' => $result['message'],
            ],
        ]);
    }

    public function ingest(
        Request $request,
        ShopeeFlexiOrderLookupService $lookup,
        ShopeeOrderPollService $poll,
        ShopeeSyncInvoicePrefix $invoicePrefix,
        ShopeeAdminOpenApiService $shopeeApi,
    ): View {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $orderSn = $lookup->normalizeOrderSn($orderRef);

        $ingest = $poll->pullAndIngestOrder($shopId, $orderSn);
        $ingest['invoice_preview'] = $invoicePrefix->invoiceForOrderSn($shopId, $orderSn);
        $ingest['omnichannel_url'] = route('omnichannel.shopee.show', ['ordersn' => $orderSn]);

        if ($ingest['error'] === null) {
            $queue = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $ingest['invoice_preview'])
                ->first();
            if ($queue !== null) {
                $ingest['jubelio_queue_url'] = route('jubelio.show', $queue);
            }
        }

        $lookupResult = $ingest['error'] === null
            ? $lookup->lookup($shopId, $orderRef)
            : null;

        return $this->lookupView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'loadDetail' => true,
            'result' => $lookupResult,
            'ingestResult' => $ingest,
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    /**
     * @param  array{orderRef: string, shopId: string, loadDetail?: bool, result: array<string, mixed>|null, ingestResult: array<string, mixed>|null, flash?: array{success: ?string, error: ?string}}  $data
     */
    private function lookupView(ShopeeAdminOpenApiService $shopeeApi, array $data): View
    {
        $status = $shopeeApi->getConnectionStatus();

        return view('shopee.flexi.lookup', array_merge($data, [
            'authorizedShopIds' => $status['authorized_shop_ids'] ?? [],
            'shopOptions' => $this->shopOptionsForLookup($status['authorized_shop_ids'] ?? [], (string) ($data['shopId'] ?? '')),
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