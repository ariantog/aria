<?php

namespace App\Http\Controllers;

use App\Models\ShopeeFlexiShop;
use App\Models\ShopeeStock;
use App\Services\Marketplace\ShopeeFlexiStagingWarehouse;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeFlexiShopController extends Controller
{
    public function index(): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $rows = ShopeeFlexiShop::query()->orderBy('shop_id')->get();
        $status = app(ShopeeAdminOpenApiService::class)->getConnectionStatus();

        return view('shopee.flexi.index', [
            'rows' => $rows,
            'authorizedShopIds' => $status['authorized_shop_ids'] ?? [],
            'stagingWarehouse' => app(ShopeeFlexiStagingWarehouse::class)->statusForUi(),
            'flash' => ['success' => session('success'), 'error' => session('fail') ?? session('error')],
        ]);
    }

    public function create(): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return view('shopee.flexi.edit', [
            'row' => new ShopeeFlexiShop([
                'direct_order_ingest' => true,
                'invoice_prefix' => 'SPMY',
            ]),
            'invoicePrefixOptions' => ShopeeSyncInvoicePrefix::selectablePrefixes(),
            'isCreate' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $data = $this->validated($request);

        if (ShopeeFlexiShop::query()->where('shop_id', $data['shop_id'])->exists()) {
            return back()->withInput()->with('error', 'Shop ID sudah ada — edit baris yang ada.');
        }

        ShopeeFlexiShop::query()->create($data);

        return redirect()->route('shopee.flexi.index')->with('success', 'FLEXI shop mapping disimpan.');
    }

    public function edit(ShopeeFlexiShop $flexi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return view('shopee.flexi.edit', [
            'row' => $flexi,
            'invoicePrefixOptions' => ShopeeSyncInvoicePrefix::selectablePrefixes(),
            'isCreate' => false,
        ]);
    }

    public function update(Request $request, ShopeeFlexiShop $flexi): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $data = $this->validated($request);

        if (ShopeeFlexiShop::query()
            ->where('shop_id', $data['shop_id'])
            ->where('id', '!=', $flexi->id)
            ->exists()) {
            return back()->withInput()->with('error', 'Shop ID sudah dipakai baris lain.');
        }

        $flexi->update($data);

        return redirect()->route('shopee.flexi.index')->with('success', 'FLEXI shop mapping diperbarui.');
    }

    public function destroy(ShopeeFlexiShop $flexi): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $flexi->delete();

        return redirect()->route('shopee.flexi.index')->with('success', 'Mapping dihapus.');
    }

    /**
     * @return array{shop_id: int, invoice_prefix: string, direct_order_ingest: bool, label: string}
     */
    private function validated(Request $request): array
    {
        $allowedPrefixes = array_keys(ShopeeSyncInvoicePrefix::selectablePrefixes());

        $request->validate([
            'shop_id' => 'required|integer|min:1',
            'invoice_prefix' => 'required|string|in:'.implode(',', $allowedPrefixes),
            'direct_order_ingest' => 'sometimes|boolean',
            'label' => 'nullable|string|max:255',
        ]);

        return [
            'shop_id' => (int) $request->input('shop_id'),
            'invoice_prefix' => strtoupper((string) $request->input('invoice_prefix')),
            'direct_order_ingest' => $request->boolean('direct_order_ingest'),
            'label' => (string) $request->input('label', ''),
        ];
    }
}