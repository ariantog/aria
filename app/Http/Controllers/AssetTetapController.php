<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\RecordFixedAssetBuy;
use App\Enums\ItemType;
use App\Http\Requests\RecordAssetTetapBuyRequest;
use App\Http\Requests\RunMonthlyDepreciationRequest;
use App\Http\Requests\StoreAssetTetapRequest;
use App\Http\Requests\UpdateAssetTetapRequest;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\BookClosingService;
use App\Services\FixedAssetService;
use App\Services\MonthlyDepreciationRunner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetTetapController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $fixedAssets,
        private readonly MonthlyDepreciationRunner $depreciationRunner,
        private readonly BookClosingService $bookClosing,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize(Item::getPermissions()['asset-tetap-view']);

        $query = Item::query()
            ->where('type', ItemType::ASSET_TETAP)
            ->with(['depreciation.warehouse'])
            ->when($request->filled('search'), fn ($q) => $q->search((string) $request->query('search')));

        $items = $query->orderByDesc('id')->paginate(50)->withQueryString();
        $rows = $this->fixedAssets->presentRegisterRows($items->getCollection());
        $items->setCollection($rows);

        return view('assettetap.index', [
            'items' => $items,
            'filters' => ['search' => (string) $request->query('search', '')],
            'can' => $this->permissions(),
        ]);
    }

    public function create()
    {
        Gate::authorize(Item::getPermissions()['asset-tetap-create']);

        return view('assettetap.create', [
            'warehouses' => $this->warehouses(),
            'minDate' => $this->bookClosing->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function store(StoreAssetTetapRequest $request)
    {
        Gate::authorize(Item::getPermissions()['asset-tetap-create']);

        $data = $request->validated();
        $this->fixedAssets->assertWarehouse(isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null);
        $item = $this->fixedAssets->createRegister($data);

        return redirect()
            ->route('assettetap.show', $item)
            ->with('success', 'Asset tetap tercatat.');
    }

    public function show(Item $item)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-view']);

        $item->load(['depreciation.warehouse', 'depreciation.buyTransaction']);
        $row = $item->depreciation;
        $accumulated = $row ? $this->fixedAssets->accumulatedDepreciation($item->id) : 0.0;
        $nbv = $row ? $this->fixedAssets->netBookValue($row, null, $accumulated) : 0.0;

        $depreciationLines = TransactionDetail::query()
            ->where('item_id', $item->id)
            ->where('transaction_type', Transaction::TYPE_DEPRECIATION)
            ->with('transaction')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(36)
            ->get();

        return view('assettetap.show', [
            'item' => $item,
            'register' => $row,
            'accumulated' => $accumulated,
            'nbv' => $nbv,
            'monthly' => $row ? $this->fixedAssets->monthlyAmount($row) : 0.0,
            'depreciationLines' => $depreciationLines,
            'can' => $this->permissions(),
        ]);
    }

    public function edit(Item $item)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-edit']);

        $item->load('depreciation');

        return view('assettetap.edit', [
            'item' => $item,
            'register' => $item->depreciation,
            'warehouses' => $this->warehouses(),
        ]);
    }

    public function update(UpdateAssetTetapRequest $request, Item $item)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-edit']);

        $data = $request->validated();
        $this->fixedAssets->assertWarehouse(isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null);
        $this->fixedAssets->updateRegister($item, $data);

        return redirect()
            ->route('assettetap.show', $item)
            ->with('success', 'Asset tetap diperbarui.');
    }

    public function destroy(Item $item)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-delete']);

        $item->delete();

        return redirect()
            ->route('assettetap.index')
            ->with('success', 'Asset tetap dihapus dari register.');
    }

    public function buy(Item $item)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-create']);

        $item->load('depreciation');
        if ($item->depreciation?->hasBuyTransaction()) {
            return redirect()
                ->route('assettetap.show', $item)
                ->with('error', 'Pembelian untuk asset ini sudah dicatat.');
        }

        return view('assettetap.buy', [
            'item' => $item,
            'register' => $item->depreciation,
            'suppliers' => $this->suppliers(),
            'warehouses' => $this->warehouses(),
            'minDate' => $this->bookClosing->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function storeBuy(RecordAssetTetapBuyRequest $request, Item $item, RecordFixedAssetBuy $action)
    {
        $this->ensureAssetTetap($item);
        Gate::authorize(Item::getPermissions()['asset-tetap-create']);

        $transaction = $action->execute($item, $request->validated());

        return redirect()
            ->route('assettetap.show', $item)
            ->with('success', 'Pembelian tercatat sebagai transaksi #'.$transaction->invoice.'.');
    }

    public function depreciate(Request $request)
    {
        Gate::authorize(Item::getPermissions()['asset-tetap-depreciate']);

        $now = Carbon::now();
        $month = (int) ($request->query('month') ?: $now->copy()->subMonthNoOverflow()->month);
        $year = (int) ($request->query('year') ?: $now->copy()->subMonthNoOverflow()->year);
        $period = Carbon::createFromDate($year, $month, 1)->startOfMonth();

        return view('assettetap.depreciate', [
            'month' => $month,
            'year' => $year,
            'yearList' => range((int) date('Y'), 2019),
            'preview' => $this->depreciationRunner->preview($period),
            'accounts' => $this->accounts(),
            'expenseAccountId' => (int) ($request->query('expense_account_id') ?: Setting::getValue(FixedAssetService::SETTING_EXPENSE_ACCOUNT, 0)),
            'contraAccountId' => (int) ($request->query('contra_account_id') ?: Setting::getValue(FixedAssetService::SETTING_CONTRA_ACCOUNT, 0)),
            'minDate' => $this->bookClosing->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function storeDepreciate(RunMonthlyDepreciationRequest $request)
    {
        Gate::authorize(Item::getPermissions()['asset-tetap-depreciate']);

        $period = Carbon::createFromDate(
            (int) $request->validated('year'),
            (int) $request->validated('month'),
            1
        )->startOfMonth();

        try {
            $result = $this->depreciationRunner->run(
                $period,
                (int) $request->validated('expense_account_id'),
                (int) $request->validated('contra_account_id'),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        if ($result['transaction'] === null) {
            return redirect()
                ->route('assettetap.depreciate', [
                    'month' => $period->month,
                    'year' => $period->year,
                ])
                ->with('success', 'Tidak ada asset yang perlu disusutkan untuk '.$period->format('Y-m').'.');
        }

        return redirect()
            ->route('transactions.show', $result['transaction'])
            ->with('success', 'Penyusutan '.$result['transaction']->invoice.' tercatat untuk '.$result['posted'].' asset.');
    }

    private function ensureAssetTetap(Item $item): void
    {
        abort_unless($item->type === ItemType::ASSET_TETAP, 404);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(): array
    {
        $p = Item::getPermissions();

        return [
            'create' => Gate::check($p['asset-tetap-create']),
            'edit' => Gate::check($p['asset-tetap-edit']),
            'delete' => Gate::check($p['asset-tetap-delete']),
            'depreciate' => Gate::check($p['asset-tetap-depreciate']),
        ];
    }

    private function warehouses()
    {
        return Addrbook::query()
            ->visibleToUser(request()->user())
            ->where('type', Addrbook::TYPE_WAREHOUSE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function suppliers()
    {
        return Addrbook::query()
            ->visibleToUser(request()->user())
            ->where('type', Addrbook::TYPE_SUPPLIER)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function accounts()
    {
        return Addrbook::query()
            ->visibleToUser(request()->user())
            ->where('type', Addrbook::TYPE_ACCOUNT)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
